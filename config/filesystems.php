<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'settings_disk' => env('SETTINGS_DISK', in_array(env('FILESYSTEM_DISK'), ['s3', 'supabase-s3'], true) ? env('FILESYSTEM_DISK') : 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3", "supabase-s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Private destination for Full Backup packages (never web-accessible).
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/private/full-backups'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Dedicated PRIVATE S3-compatible destination for backups (AWS S3, Backblaze B2, Cloudflare R2...).
        // Separate credentials and bucket from the media bucket. Inert until BACKUP_S3_* are set and BACKUP_DISK=backups-s3.
        'backups-s3' => [
            // 's3-noacl': standard S3 client without ACL headers (works on buckets with ACLs disabled, the AWS default).
            'driver' => 's3-noacl',
            'key' => env('BACKUP_S3_KEY'),
            'secret' => env('BACKUP_S3_SECRET'),
            'region' => env('BACKUP_S3_REGION', 'us-east-1'),
            'bucket' => env('BACKUP_S3_BUCKET'),
            'endpoint' => env('BACKUP_S3_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('BACKUP_S3_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
            'options' => array_filter([
                // Server-side encryption at rest (AES256 or aws:kms). Packages are also encrypted by the application.
                'ServerSideEncryption' => env('BACKUP_S3_SSE', 'AES256'),
            ]),
            'http' => [
                'connect_timeout' => 10,
                'timeout' => 300,
            ],
            // Optional S3 Object Lock applied to packages/ uploads (bucket must have Object Lock enabled).
            // BACKUP_S3_OBJECT_LOCK_MODE = GOVERNANCE | COMPLIANCE (empty = off). Days per retention class tag.
            'object_lock' => [
                'mode' => env('BACKUP_S3_OBJECT_LOCK_MODE'),
                'days' => [
                    'daily' => (int) env('BACKUP_LOCK_DAYS_DAILY', 7),
                    'weekly' => (int) env('BACKUP_LOCK_DAYS_WEEKLY', 35),
                    'monthly' => (int) env('BACKUP_LOCK_DAYS_MONTHLY', 400),
                ],
            ],
        ],

        's3' => [
            'driver' => env('AWS_DRIVER') ?: 'supabase-s3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION') ?: 'ap-northeast-1',
            'bucket' => env('AWS_BUCKET') ?: 'bakery-media',
            'url' => (function () {
                $bucket = env('AWS_BUCKET') ?: 'bakery-media';
                $url = env('AWS_URL');
                if ($url) {
                    if (str_contains($url, '.supabase.co/storage/v1/object/public') && !str_ends_with(rtrim($url, '/'), '/' . $bucket)) {
                        return rtrim($url, '/') . '/' . $bucket;
                    }
                    return $url;
                }
                $endpoint = env('AWS_ENDPOINT');
                if ($endpoint && str_contains($endpoint, '.storage.supabase.co')) {
                    return str_replace('.storage.supabase.co/storage/v1/s3', '.supabase.co/storage/v1/object/public/' . $bucket, $endpoint);
                }
                return null;
            })(),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_ENDPOINT') ? true : false),
            'throw' => true,
            'report' => true,
        ],

        'supabase-s3' => [
            'driver' => 'supabase-s3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION') ?: 'ap-northeast-1',
            'bucket' => env('AWS_BUCKET') ?: 'bakery-media',
            'url' => (function () {
                $bucket = env('AWS_BUCKET') ?: 'bakery-media';
                $url = env('AWS_URL');
                if ($url) {
                    if (str_contains($url, '.supabase.co/storage/v1/object/public') && !str_ends_with(rtrim($url, '/'), '/' . $bucket)) {
                        return rtrim($url, '/') . '/' . $bucket;
                    }
                    return $url;
                }
                $endpoint = env('AWS_ENDPOINT');
                if ($endpoint && str_contains($endpoint, '.storage.supabase.co')) {
                    return str_replace('.storage.supabase.co/storage/v1/s3', '.supabase.co/storage/v1/object/public/' . $bucket, $endpoint);
                }
                return null;
            })(),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_ENDPOINT') ? true : false),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
