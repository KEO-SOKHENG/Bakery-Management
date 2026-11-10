<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorageDiagnosticsService
{

    /**
     * Determine the active storage disk for persistent media assets.
     */
    public function getActiveDiskName(): string
    {
        $settingsDisk = config('filesystems.settings_disk');
        if (!empty($settingsDisk) && config("filesystems.disks.{$settingsDisk}")) {
            return $settingsDisk;
        }

        $defaultDisk = config('filesystems.default');
        if (in_array($defaultDisk, ['supabase-s3', 's3'], true) && config("filesystems.disks.{$defaultDisk}")) {
            return $defaultDisk;
        }

        return 'public';
    }

    // ── Provider Configuration ──

    /**
     * Retrieve verified storage provider configuration without leaking credentials.
     */
    public function getProviderConfig(?string $diskName = null): array
    {
        $diskName = $diskName ?: $this->getActiveDiskName();
        $config = config("filesystems.disks.{$diskName}", []);
        $driver = (string) ($config['driver'] ?? $diskName);
        $endpoint = (string) ($config['endpoint'] ?? '');
        $url = (string) ($config['url'] ?? '');
        $bucket = $config['bucket'] ?? null;
        $region = $config['region'] ?? null;
        $isProduction = app()->environment('production');

        // Classify provider based on verified driver and endpoint configuration
        if ($driver === 'supabase-s3' || str_contains($endpoint, 'supabase.co') || str_contains($url, 'supabase.co')) {
            $provider = 'Supabase Storage (S3-compatible)';
        } elseif ($driver === 's3') {
            $provider = 'Amazon S3 (or S3-compatible)';
        } elseif ($driver === 'local' && $diskName === 'public') {
            $provider = $isProduction
                ? 'Local Public Filesystem (Ephemeral Container Storage)'
                : 'Local Public Filesystem (Development)';
        } elseif ($driver === 'local') {
            $provider = $isProduction
                ? 'Local Filesystem (Ephemeral Container Storage)'
                : 'Local Filesystem (Development)';
        } else {
            $provider = ucfirst($driver) . ' Storage';
        }

        // Sanitize endpoint to host only — NEVER expose credentials, tokens, or query strings
        $endpointHost = null;
        if (!empty($endpoint)) {
            $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;
        } elseif ($driver === 'local') {
            $endpointHost = $isProduction ? 'Render Container Local Disk' : 'Local Server Disk';
        }

        $isPublic = false;
        if (($config['visibility'] ?? null) === 'public' || !empty($url)) {
            $isPublic = true;
        }

        return [
            'provider'       => $provider,
            'driver'         => $driver,
            'disk_name'      => $diskName,
            'default_disk'   => config('filesystems.default', 'local'),
            'bucket'         => !empty($bucket) ? (string) $bucket : 'Data unavailable',
            'region'         => !empty($region) ? (string) $region : 'Data unavailable',
            'endpoint_host'  => $endpointHost ?: 'Data unavailable',
            'public_access'  => $isPublic ? 'Public (Read-only object access)' : 'Private access',
            'is_public'      => $isPublic,
            'is_production'  => $isProduction,
        ];
    }

    // ── Connection Monitoring ──

    /**
     * Perform a safe, read-only connectivity check against the target storage disk.
     */
    public function checkConnection(?string $diskName = null): array
    {
        $diskName = $diskName ?: $this->getActiveDiskName();
        $startTime = microtime(true);
        $isProduction = app()->environment('production');

        $diskConfig = config("filesystems.disks.{$diskName}", []);
        $driverName = $diskConfig['driver'] ?? $diskName;

        // 1. Strict Production Safeguard: Never allow ephemeral local disk in production
        if ($isProduction && in_array($driverName, ['local'], true)) {
            return [
                'status'       => 'Configuration mismatch',
                'is_connected' => false,
                'latency_ms'   => null,
                'message'      => "Production environment requires persistent cloud storage (Supabase S3), but is currently configured with ephemeral local disk [{$diskName}]. Uploaded media will not persist across container deploys.",
                'checked_at'   => now()->toIso8601String(),
            ];
        }

        // 2. S3 / Supabase credential & configuration validation
        if (in_array($driverName, ['s3', 'supabase-s3'], true)) {
            $key = $diskConfig['key'] ?? null;
            $secret = $diskConfig['secret'] ?? null;
            $bucket = $diskConfig['bucket'] ?? null;

            if (empty($key) || empty($secret)) {
                return [
                    'status'       => 'Configuration mismatch',
                    'is_connected' => false,
                    'latency_ms'   => null,
                    'message'      => 'Missing required S3 credentials (AWS_ACCESS_KEY_ID or AWS_SECRET_ACCESS_KEY).',
                    'checked_at'   => now()->toIso8601String(),
                ];
            }

            if (empty($bucket)) {
                return [
                    'status'       => 'Configuration mismatch',
                    'is_connected' => false,
                    'latency_ms'   => null,
                    'message'      => 'Missing required S3 bucket name (AWS_BUCKET).',
                    'checked_at'   => now()->toIso8601String(),
                ];
            }
        }

        try {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);

            // Safe read-only inspection using iterator. We break after inspecting accessibility.
            // Never uploads or deletes test files during health checks.
            $driver = $disk->getDriver();
            $iterator = $driver->listContents('', false);
            foreach ($iterator as $item) {
                break;
            }

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            $status = 'Connected';
            $message = 'Storage endpoint and bucket are operational and reachable.';

            // Distinguish local dev connection from cloud storage
            if (in_array($driverName, ['local'], true) && !$isProduction) {
                $status = 'Connected (Local Dev)';
                $message = 'Local development storage disk is operational. (Production uses Supabase S3 bucket "bakery-media").';
            }

            return [
                'status'       => $status,
                'is_connected' => true,
                'latency_ms'   => $latencyMs,
                'message'      => $message,
                'checked_at'   => now()->toIso8601String(),
            ];
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);

            // Safe read-only inspection using iterator. We break after inspecting accessibility.
            // Never uploads or deletes test files during health checks.
            $driver = $disk->getDriver();
            $iterator = $driver->listContents('', false);
            foreach ($iterator as $item) {
                break;
            }

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status'       => 'Connected',
                'is_connected' => true,
                'latency_ms'   => $latencyMs,
                'message'      => 'Storage endpoint and bucket are operational and reachable.',
                'checked_at'   => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $status = 'Connection failed';

            if (str_contains($msg, 'AccessDenied') || str_contains($msg, '403 Forbidden') || str_contains($msg, 'Permission denied')) {
                $status = 'Permission denied';
            } elseif (str_contains($msg, 'InvalidAccessKeyId') || str_contains($msg, 'SignatureDoesNotMatch') || str_contains($msg, 'InvalidCredentials') || str_contains($msg, '401 Unauthorized')) {
                $status = 'Authentication failed';
            } elseif (str_contains($msg, 'NoSuchBucket') || str_contains($msg, '404 Not Found')) {
                $status = 'Connection failed';
            } elseif (str_contains($msg, 'Driver [] is not supported') || str_contains($msg, 'Disk [] is not configured')) {
                $status = 'Data unavailable';
            }

            return [
                'status'       => $status,
                'is_connected' => false,
                'latency_ms'   => null,
                'message'      => $msg,
                'checked_at'   => now()->toIso8601String(),
            ];
        }
    }

    // ── Object Metadata & Usage Analysis ──

    /**
     * Calculate actual storage usage and object breakdown from verified object metadata.
     */
    public function getStorageUsage(?string $diskName = null): array
    {
        $diskName = $diskName ?: $this->getActiveDiskName();
        $isProduction = app()->environment('production');

        $diskConfig = config("filesystems.disks.{$diskName}", []);
        $driverName = $diskConfig['driver'] ?? $diskName;

        if ($isProduction && in_array($driverName, ['local'], true)) {
            return [
                'total_files'           => 0,
                'total_bytes'           => 0,
                'total_bytes_formatted' => 'Data unavailable',
                'quota_bytes'           => null,
                'quota_formatted'       => 'Data unavailable',
                'quota_percent'         => null,
                'quota_notice'          => 'Production environment cannot use local ephemeral disk. Persistent Supabase S3 storage required.',
                'categories'            => [],
                'file_types'            => [],
                'recent_files'          => [],
                'largest_files'         => [],
                'error'                 => 'Configuration mismatch: local disk in production',
            ];
        }

        if (in_array($driverName, ['s3', 'supabase-s3'], true)) {
            $key = $diskConfig['key'] ?? null;
            $secret = $diskConfig['secret'] ?? null;
            $bucket = $diskConfig['bucket'] ?? null;
            if (empty($key) || empty($secret) || empty($bucket)) {
                return [
                    'total_files'           => 0,
                    'total_bytes'           => 0,
                    'total_bytes_formatted' => 'Data unavailable',
                    'quota_bytes'           => null,
                    'quota_formatted'       => 'Data unavailable',
                    'quota_percent'         => null,
                    'quota_notice'          => 'Missing required S3 credentials or bucket name (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET).',
                    'categories'            => [],
                    'file_types'            => [],
                    'recent_files'          => [],
                    'largest_files'         => [],
                    'error'                 => 'Missing S3 credentials or bucket',
                ];
            }
        }

        try {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);
            $driver = $disk->getDriver();

            $files = [];
            $totalBytes = 0;

            $categoryMap = [
                'logos'      => ['name' => 'Logos',           'prefix' => 'logos/',      'count' => 0, 'bytes' => 0],
                'products'   => ['name' => 'Product Images',  'prefix' => 'products/',   'count' => 0, 'bytes' => 0],
                'categories' => ['name' => 'Category Images', 'prefix' => 'categories/', 'count' => 0, 'bytes' => 0],
                'avatars'    => ['name' => 'User Avatars',    'prefix' => 'avatars/',    'count' => 0, 'bytes' => 0],
                'receipts'   => ['name' => 'Receipts',        'prefix' => 'receipts/',   'count' => 0, 'bytes' => 0],
                'backups'    => ['name' => 'System Backups',  'prefix' => 'backups/',    'count' => 0, 'bytes' => 0],
                'other'      => ['name' => 'Other Media',     'prefix' => '',            'count' => 0, 'bytes' => 0],
            ];
            $extensionMap = [];

            $contents = $driver->listContents('', true);

            foreach ($contents as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $path = $item->path();
                // Exclude system dot-files if any
                if (basename($path) === '.gitignore') {
                    continue;
                }

                $size = $item->fileSize() ?? 0;
                $totalBytes += $size;

                // Match storage path to category
                $assignedCategoryKey = 'other';
                foreach ($categoryMap as $catKey => $catInfo) {
                    if ($catKey !== 'other' && str_starts_with($path, $catInfo['prefix'])) {
                        $assignedCategoryKey = $catKey;
                        break;
                    }
                }
                $categoryMap[$assignedCategoryKey]['count']++;
                $categoryMap[$assignedCategoryKey]['bytes'] += $size;

                // Extension breakdown
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';
                if (!isset($extensionMap[$ext])) {
                    $extensionMap[$ext] = ['extension' => strtoupper($ext), 'count' => 0, 'bytes' => 0];
                }
                $extensionMap[$ext]['count']++;
                $extensionMap[$ext]['bytes'] += $size;

                $lastModifiedTimestamp = $item->lastModified() ?? 0;

                // Public URL if resolvable
                $url = null;
                try {
                    $url = $disk->url($path);
                    if (str_contains($url, '.storage.supabase.co/storage/v1/s3/')) {
                        $url = str_replace('.storage.supabase.co/storage/v1/s3/', '.supabase.co/storage/v1/object/public/', $url);
                    }
                } catch (\Throwable) {
                    $url = null;
                }

                $files[] = [
                    'name'                    => basename($path),
                    'path'                    => $path,
                    'category'                => $categoryMap[$assignedCategoryKey]['name'],
                    'category_key'            => $assignedCategoryKey,
                    'size_bytes'              => $size,
                    'size_formatted'          => self::formatBytes($size),
                    'mime_type'               => $item->mimeType() ?: self::guessMimeType($ext),
                    'last_modified'           => $lastModifiedTimestamp > 0 ? date('c', $lastModifiedTimestamp) : null,
                    'last_modified_formatted' => $lastModifiedTimestamp > 0 ? date('M d, Y h:i A', $lastModifiedTimestamp) : 'Data unavailable',
                    'last_modified_timestamp' => $lastModifiedTimestamp,
                    'url'                     => $url,
                ];
            }

            // Compile categories that have verified objects or application conventions
            $activeCategories = [];
            foreach ($categoryMap as $catKey => $data) {
                if ($data['count'] > 0 || in_array($catKey, ['logos', 'products', 'categories', 'avatars', 'receipts'], true)) {
                    $pct = $totalBytes > 0 ? round(($data['bytes'] / $totalBytes) * 100, 1) : 0;
                    $activeCategories[] = [
                        'key'             => $catKey,
                        'name'            => $data['name'],
                        'prefix'          => $data['prefix'],
                        'count'           => $data['count'],
                        'bytes'           => $data['bytes'],
                        'bytes_formatted' => self::formatBytes($data['bytes']),
                        'percentage'      => $pct,
                    ];
                }
            }

            // Sort file types by bytes descending
            uasort($extensionMap, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
            $fileTypes = array_values(array_map(function ($ft) use ($totalBytes) {
                $ft['bytes_formatted'] = self::formatBytes($ft['bytes']);
                $ft['percentage'] = $totalBytes > 0 ? round(($ft['bytes'] / $totalBytes) * 100, 1) : 0;
                return $ft;
            }, $extensionMap));

            // Sort recent files by last modified DESC
            usort($files, fn($a, $b) => $b['last_modified_timestamp'] <=> $a['last_modified_timestamp']);
            $recentFiles = array_slice($files, 0, 10);

            // Sort largest files by size DESC
            $largestFiles = $files;
            usort($largestFiles, fn($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);
            $largestFiles = array_slice($largestFiles, 0, 5);

            return [
                'total_files'           => count($files),
                'total_bytes'           => $totalBytes,
                'total_bytes_formatted' => self::formatBytes($totalBytes),
                'quota_bytes'           => null,
                'quota_formatted'       => 'Unavailable (Unconstrained)',
                'quota_percent'         => null,
                'quota_notice'          => 'Supabase S3 API does not expose account storage quota. Usage is unconstrained.',
                'categories'            => $activeCategories,
                'file_types'            => $fileTypes,
                'recent_files'          => $recentFiles,
                'largest_files'         => $largestFiles,
                'error'                 => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('StorageDiagnosticsService query failed: ' . $e->getMessage());

            return [
                'total_files'           => 0,
                'total_bytes'           => 0,
                'total_bytes_formatted' => 'Data unavailable',
                'quota_bytes'           => null,
                'quota_formatted'       => 'Data unavailable',
                'quota_percent'         => null,
                'quota_notice'          => 'Storage metadata query failed: ' . $e->getMessage(),
                'categories'            => [],
                'file_types'            => [],
                'recent_files'          => [],
                'largest_files'         => [],
                'error'                 => $e->getMessage(),
            ];
        }
    }

    // ── Aggregated Diagnostics API ──

    /**
     * Full aggregated diagnostics response with caching and forced refresh support.
     */
    public function getDiagnostics(bool $forceRefresh = false): array
    {
        $diskName = $this->getActiveDiskName();
        $cacheKey = "storage_diagnostics_{$diskName}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 30, function () use ($diskName) {
            $providerConfig = $this->getProviderConfig($diskName);
            $connection = $this->checkConnection($diskName);
            $usage = $this->getStorageUsage($diskName);

            return [
                'success'             => true,
                'provider'            => $providerConfig,
                'connection'          => $connection,
                'usage'               => $usage,
                'display'             => $this->buildDisplaySummary($providerConfig, $connection, $usage),
                'synced_at'           => now()->toIso8601String(),
                'synced_at_formatted' => now()->format('h:i:s A'),
            ];
        });
    }

    // ── Display Summary ──

    /**
     * Plain-language summary for the Storage & Files page header panel.
     * Provider and environment are derived only from verified configuration.
     */
    public function buildDisplaySummary(array $provider, array $connection, array $usage): array
    {
        $driver = (string) ($provider['driver'] ?? '');
        $providerText = (string) ($provider['provider'] ?? '');

        if ($driver === 'supabase-s3' || str_contains($providerText, 'Supabase')) {
            $providerLabel = 'Supabase Storage';
        } elseif ($driver === 'local') {
            $providerLabel = 'Local Storage';
        } elseif ($driver === 's3') {
            $providerLabel = 'S3-compatible Storage';
        } else {
            $providerLabel = $providerText !== '' ? $providerText : 'Data unavailable';
        }

        $environment = !empty($provider['is_production']) ? 'Production' : 'Development';

        $status = (string) ($connection['status'] ?? 'Data unavailable');
        if (!empty($connection['is_connected'])) {
            $state = 'connected';
            $badge = 'Connected';
            $headline = 'Storage Connected';
        } elseif ($status === 'Data unavailable') {
            $state = 'unavailable';
            $badge = 'Data unavailable';
            $headline = 'Data unavailable';
        } else {
            $state = 'disconnected';
            $badge = 'Disconnected';
            $headline = 'Storage Disconnected';
        }

        $hasUsage = empty($usage['error']);

        return [
            'state'           => $state,
            'badge'           => $badge,
            'headline'        => $headline,
            'provider_label'  => $providerLabel,
            'environment'     => $environment,
            'subtitle'        => "{$providerLabel} ({$environment})",
            'detail'          => $state === 'connected' ? null : ($connection['message'] ?? null),
            'usage_available' => $hasUsage,
        ];
    }

    // ── Formatting & Utility Helpers ──

    /**
     * Convert byte count into readable binary units (B, KB, MB, GB, TB).
     */
    public static function formatBytes(int|float $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / pow(1024, $power);

        return round($value, $precision) . ' ' . $units[$power];
    }

    /**
     * Derive MIME type from file extension.
     */
    public static function guessMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'svg'         => 'image/svg+xml',
            'json'        => 'application/json',
            'pdf'         => 'application/pdf',
            'txt'         => 'text/plain',
            'csv'         => 'text/csv',
            default       => 'application/octet-stream',
        };
    }
}

