<?php

namespace App\Console\Commands;

use App\Services\StorageDiagnosticsService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'storage:diagnostic')]
class StorageDiagnosticCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:diagnostic {--disk= : The storage disk to inspect} {--json : Output information as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely check storage provider connectivity and inspect object metadata without modifying data';

    /**
     * Execute the console command.
     */
    public function handle(StorageDiagnosticsService $service): int
    {
        $diskName = $this->option('disk') ?: $service->getActiveDiskName();
        $provider = $service->getProviderConfig($diskName);
        $connection = $service->checkConnection($diskName);
        $usage = $connection['is_connected'] ? $service->getStorageUsage($diskName) : null;

        $payload = [
            'disk'        => $diskName,
            'provider'    => $provider['provider'],
            'driver'      => $provider['driver'],
            'bucket'      => $provider['bucket'],
            'region'      => $provider['region'],
            'endpoint'    => $provider['endpoint_host'],
            'status'      => $connection['status'],
            'connected'   => $connection['is_connected'],
            'latency_ms'  => $connection['latency_ms'],
            'message'     => $connection['message'],
            'total_files' => $usage['total_files'] ?? 0,
            'total_bytes' => $usage['total_bytes'] ?? 0,
            'formatted'   => $usage['total_bytes_formatted'] ?? 'Data unavailable',
            'categories'  => $usage['categories'] ?? [],
            'recent'      => array_slice($usage['recent_files'] ?? [], 0, 5),
        ];

        if ($this->option('json')) {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT));
            return $connection['is_connected'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Storage Diagnostic Inspection');
        $this->components->twoColumnDetail('Active Disk', $diskName);
        $this->components->twoColumnDetail('Provider', $provider['provider']);
        $this->components->twoColumnDetail('Driver', $provider['driver']);
        $this->components->twoColumnDetail('Bucket', $provider['bucket']);
        $this->components->twoColumnDetail('Region', $provider['region']);
        $this->components->twoColumnDetail('Endpoint Host', $provider['endpoint_host']);

        $statusColor = $connection['is_connected'] ? 'green' : (str_contains($connection['status'], 'mismatch') ? 'yellow' : 'red');
        $this->components->twoColumnDetail('Status', "<fg={$statusColor}>{$connection['status']}</>");
        $this->components->twoColumnDetail('Latency', $connection['latency_ms'] !== null ? "{$connection['latency_ms']} ms" : 'N/A');
        $this->components->twoColumnDetail('Diagnostic Message', $connection['message']);

        if ($usage) {
            $this->newLine();
            $this->components->info('Storage Usage & Media Assets');
            $this->components->twoColumnDetail('Total Files', (string) $usage['total_files']);
            $this->components->twoColumnDetail('Total Consumed', "{$usage['total_bytes_formatted']} ({$usage['total_bytes']} bytes)");
            $this->components->twoColumnDetail('Quota Status', $usage['quota_formatted']);

            if (!empty($usage['categories'])) {
                $this->newLine();
                $this->line(' <fg=gray>Categories:</>');
                foreach ($usage['categories'] as $cat) {
                    $this->components->twoColumnDetail("  {$cat['name']}", "{$cat['count']} files ({$cat['bytes_formatted']})");
                }
            }

            if (!empty($usage['recent_files'])) {
                $this->newLine();
                $this->line(' <fg=gray>Recent Files:</>');
                foreach (array_slice($usage['recent_files'], 0, 3) as $f) {
                    $this->components->twoColumnDetail("  {$f['name']}", "{$f['size_formatted']} [{$f['category']}]");
                }
            }
        }

        $this->newLine();
        return $connection['is_connected'] ? self::SUCCESS : self::FAILURE;
    }
}

