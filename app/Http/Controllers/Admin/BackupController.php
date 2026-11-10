<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunFullBackupJob;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Backup\BackupDestinationGuard;
use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupSpaceGuard;
use App\Services\Backup\BackupRedactor;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use App\Services\Backup\ProductionRestoreGate;
use App\Services\Backup\SandboxRestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Full Backup & Restore (Admin only). Production restore is intentionally not implemented.
 */
class BackupController extends Controller
{
    public function __construct(
        private BackupHistoryService $history,
        private BackupVerificationService $verification,
        private SandboxRestoreService $sandbox,
        private ProductionRestoreGate $gate,
        private BackupEncryptionService $encryption,
        private BackupDestinationGuard $guard,
        private BackupLock $backupLock,
        private BackupSpaceGuard $space,
    ) {
    }

    private function admin(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isAdmin(), 403, 'Only system administrators can manage backups.');

        return $user;
    }

    /** Backup history (real records only). */
    public function index(): JsonResponse
    {
        $this->admin();
        $this->history->reapStale();

        $records = array_map(function (array $r) {
            $r['download_url'] = (!empty($r['verified']) && $this->history->packageExists($r['id']))
                ? URL::temporarySignedRoute('admin.backups.download', now()->addMinutes((int) config('backup.download_link_minutes', 5)), ['id' => $r['id']])
                : null;
            unset($r['verification_steps']);

            return $r;
        }, $this->history->all());

        return response()->json(['success' => true, 'backups' => $records, 'destination' => $this->history->destinationLabel()]);
    }

    /** Start a Full Backup. Returns immediately with the backup id; poll status(). */
    public function store(): JsonResponse
    {
        $user = $this->admin();

        $destination = $this->guard->inspect();
        if (!$destination['ok']) {
            return response()->json(['success' => false, 'message' => 'Backups cannot be created yet: ' . implode(' ', $destination['problems'])], 422);
        }

        $this->history->reapStale();
        if ($this->backupLock->isHeld()) {
            return response()->json(['success' => false, 'message' => 'Another backup is already running.'], 409);
        }

        $id = $this->history->newId();
        $actor = ['user_id' => $user->id, 'user_label' => $user->name];

        $this->history->setProgress($id, ['status' => 'running', 'components' => [
            'database' => ['status' => 'pending'], 'media' => ['status' => 'pending'], 'settings' => ['status' => 'pending'],
            'package' => ['status' => 'pending'], 'verification' => ['status' => 'pending'],
        ]]);

        AuditLogService::log('full_backup_started', "Administrator {$user->name} started a full backup.", null, ['backup_id' => $id]);

        if (config('queue.default') === 'sync') {
            RunFullBackupJob::dispatchAfterResponse($id, $actor);
        } else {
            RunFullBackupJob::dispatch($id, $actor);
        }

        return response()->json(['success' => true, 'backup_id' => $id], 202);
    }

    public function status(string $id): JsonResponse
    {
        $this->admin();
        $this->history->reapStale();
        $record = $this->history->find($id);
        $progress = $this->history->getProgress($id);
        if (!$record && !$progress) {
            return response()->json(['success' => false, 'message' => 'Backup not found.'], 404);
        }

        return response()->json(['success' => true, 'backup_id' => $id, 'progress' => $progress, 'record' => $record ? array_diff_key($record, ['verification_steps' => 1]) : null]);
    }

    /** Short-lived signed download of the encrypted package (never a public URL). */
    public function download(string $id)
    {
        $user = $this->admin();
        $record = $this->history->find($id);
        abort_unless($record && !empty($record['verified']) && $this->history->packageExists($id), 404);
        try {
            $this->space->assertFor((int) ($record['package_size'] ?? 0), 'download the package', BackupSpaceGuard::DOWNLOAD);
        } catch (\RuntimeException $e) {
            abort(507, $e->getMessage());
        }

        AuditLogService::log('full_backup_downloaded', "Administrator {$user->name} downloaded backup {$id}.", null, ['backup_id' => $id]);

        $name = 'bakery_full_backup_' . date('Ymd_His', strtotime($record['created_at'])) . '_' . substr($id, 0, 8) . '.bkp';

        return $this->history->disk()->download($this->history->packagePath($id), $name, ['Content-Type' => 'application/octet-stream']);
    }

    /** Steps 1-3: select, verify and preview a package (existing backup id or an uploaded file). */
    public function verify(Request $request): JsonResponse
    {
        $user = $this->admin();
        $maxKb = (int) config('backup.max_upload_mb') * 1024;

        $request->validate([
            'backup_id' => ['nullable', 'uuid'],
            'package' => ['nullable', 'file', 'max:' . $maxKb],
        ]);
        if (!$request->filled('backup_id') && !$request->hasFile('package')) {
            return response()->json(['success' => false, 'message' => 'Choose a backup from the list or upload a package.'], 422);
        }

        $local = null;
        try {
            if ($request->hasFile('package')) {
                $file = $request->file('package');
                if (!$file->isValid()) {
                    $tooBig = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

                    return response()->json(['success' => false, 'message' => $tooBig
                        ? 'The file is larger than the server upload limit (upload_max_filesize / post_max_size). Ask your administrator to raise it, or restore from the backup history instead.'
                        : 'The upload failed. Please try again.'], $tooBig ? 413 : 422);
                }
                if (!$this->encryption->isEncrypted($file->getRealPath())) {
                    return response()->json(['success' => false, 'message' => 'This is not a supported Bakery backup package (.bkp).'], 422);
                }
                $local = $file->getRealPath();
                $this->space->assertFor((int) $file->getSize(), 'verify the package', BackupSpaceGuard::VERIFY);
                $verify = $this->verification->verifyFile($local);
                $id = null;
                if ($verify['ok']) {
                    $id = $this->history->newId();
                    $stream = fopen($local, 'rb');
                    $this->history->disk()->writeStream($this->history->packagePath($id), $stream);
                    fclose($stream);
                    $this->history->save($this->importedRecord($id, $verify, filesize($local), $user));
                }
            } else {
                $id = (string) $request->input('backup_id');
                $record = $this->history->find($id);
                if (!$record || !$this->history->packageExists($id)) {
                    return response()->json(['success' => false, 'message' => 'Backup not found.'], 404);
                }
                $this->space->assertFor((int) ($record['package_size'] ?? 0), 'verify the package', BackupSpaceGuard::VERIFY);
                $dir = BackupWorkspace::make('fetch');
                $local = $dir . DIRECTORY_SEPARATOR . 'package.bkp';
                $in = $this->history->disk()->readStream($this->history->packagePath($id));
                $out = fopen($local, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $verify = $this->verification->verifyFile($local);
                BackupWorkspace::destroy($dir);
                $this->history->update($id, [
                    'verified' => $verify['ok'],
                    'verified_at' => $verify['ok'] ? now()->toIso8601String() : null,
                ]);
            }

            $preview = $verify['ok'] && $verify['manifest'] ? $this->verification->preview($verify['manifest']) : null;
            BackupWorkspace::destroy($verify['work_dir']);

            AuditLogService::log('backup_verified', "Administrator {$user->name} verified a backup package.", null, ['backup_id' => $id, 'ok' => $verify['ok']]);

            return response()->json(['success' => true, 'ok' => $verify['ok'], 'backup_id' => $verify['ok'] ? $id : null, 'steps' => $verify['steps'], 'preview' => $preview]);
        } catch (Throwable $e) {
            $noSpace = str_starts_with($e->getMessage(), 'Not enough free disk space');

            return response()->json(['success' => false, 'message' => BackupRedactor::redact($e->getMessage())], $noSpace ? 507 : 500);
        }
    }

    /** Step 4: restore into an isolated sandbox. Never touches Production. */
    public function sandboxRestore(string $id): JsonResponse
    {
        $user = $this->admin();
        AuditLogService::log('sandbox_restore_started', "Administrator {$user->name} started a sandbox restore rehearsal.", null, ['backup_id' => $id]);

        $report = $this->sandbox->rehearse($id);

        AuditLogService::log('sandbox_restore_finished', 'Sandbox restore rehearsal finished.', null, ['backup_id' => $id, 'ok' => $report['ok']]);

        return response()->json(['success' => true, 'ok' => $report['ok'], 'report' => $report]);
    }

    /** Step 5: Production restore is disabled and always rejected. */
    public function productionRestore(Request $request): JsonResponse
    {
        $user = $this->admin();
        $gate = $this->gate->evaluate($request->input('backup_id'));

        AuditLogService::log('production_restore_rejected', "Production restore attempt by {$user->name} was rejected (feature disabled).", null, ['backup_id' => $request->input('backup_id')]);

        return response()->json([
            'success' => false,
            'message' => 'Production restore is disabled. It requires separate, explicit authorization by the system owner.',
            'conditions' => $gate['conditions'],
        ], 403);
    }

    /** Gate status for the UI (read-only). */
    public function productionGate(Request $request): JsonResponse
    {
        $this->admin();

        return response()->json(['success' => true] + $this->gate->evaluate($request->query('backup_id')));
    }

    private function importedRecord(string $id, array $verify, int $size, User $user): array
    {
        $manifest = $verify['manifest'] ?? [];

        return [
            'id' => $id,
            'type' => 'imported',
            'created_at' => (string) ($manifest['created_at'] ?? now()->toIso8601String()),
            'status' => 'completed',
            'verified' => true,
            'verified_at' => now()->toIso8601String(),
            'destination' => $this->history->destinationLabel(),
            'created_by' => $user->name,
            'filename' => basename($this->history->packagePath($id)),
            'package_size' => $size,
            'database_size' => (int) ($manifest['database']['size'] ?? 0),
            'media_count' => (int) ($manifest['media']['count'] ?? 0),
            'media_bytes' => (int) ($manifest['media']['bytes'] ?? 0),
            'settings_count' => (int) ($manifest['settings']['count'] ?? 0),
            'source_backup_id' => $manifest['backup_id'] ?? null,
            'components' => [],
        ];
    }
}
