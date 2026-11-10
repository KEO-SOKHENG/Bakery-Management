<?php

namespace App\Services\Backup;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Settings-only export. Secrets are filtered out by key pattern; nothing from the
 * environment, users table or session store is ever included.
 */
class SettingsBackupService
{
    public const SCHEMA = 'bakery.settings.v1';

    /** @return array{schema:string,created_at:string,settings:array<string,string|null>,excluded_count:int} */
    public function export(): array
    {
        $settings = [];
        $excluded = 0;
        foreach (Setting::query()->orderBy('key')->get(['key', 'value']) as $row) {
            if ($this->isSensitive((string) $row->key)) {
                $excluded++;
                continue;
            }
            $settings[(string) $row->key] = $row->value;
        }

        return [
            'schema' => self::SCHEMA,
            'created_at' => now()->toIso8601String(),
            'settings' => $settings,
            'excluded_count' => $excluded,
        ];
    }

    public function isSensitive(string $key): bool
    {
        return (bool) preg_match((string) config('backup.sensitive_setting_pattern'), $key);
    }

    /** @return array<int,string> validation errors (empty = valid) */
    public function validate(mixed $payload): array
    {
        $errors = [];
        if (!is_array($payload)) {
            return ['Settings file is not a JSON object.'];
        }
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            $errors[] = 'Unsupported settings schema.';
        }
        if (!isset($payload['settings']) || !is_array($payload['settings'])) {
            return array_merge($errors, ['Settings list is missing.']);
        }
        foreach ($payload['settings'] as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.\-]{1,120}$/', $key)) {
                $errors[] = 'Invalid setting key.';
                continue;
            }
            if ($this->isSensitive($key)) {
                $errors[] = "Sensitive setting '{$key}' must not be present.";
            }
            if (!is_null($value) && !is_scalar($value)) {
                $errors[] = "Setting '{$key}' has an unsupported value type.";
            } elseif (is_string($value) && strlen($value) > 65535) {
                $errors[] = "Setting '{$key}' is too large.";
            }
        }

        return $errors;
    }

    /**
     * Import into a specific (sandbox) connection and verify every value.
     *
     * @return array{imported:int, mismatches:array<int,string>}
     */
    public function importAndVerify(string $connection, array $settings): array
    {
        $db = DB::connection($connection);
        $imported = 0;
        $mismatches = [];

        foreach ($settings as $key => $value) {
            $value = is_null($value) ? null : (string) $value;
            $existing = $db->table('settings')->where('key', $key)->first();
            if ($existing) {
                $db->table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => now()]);
            } else {
                $db->table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
            $imported++;
        }
        foreach ($settings as $key => $value) {
            $stored = $db->table('settings')->where('key', $key)->value('value');
            if ((string) $stored !== (string) ($value ?? '')) {
                $mismatches[] = $key;
            }
        }

        return ['imported' => $imported, 'mismatches' => $mismatches];
    }
}
