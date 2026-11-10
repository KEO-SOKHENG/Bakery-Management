<?php

namespace App\Providers;

use App\Models\Notification;
use App\Services\NotificationService;
use Aws\AwsClientInterface;
use Aws\CommandInterface;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelS3Adapter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemS3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;
use Psr\Http\Message\RequestInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Set Bakery System custom pagination views with safe fallback
        if (View::exists('vendor.pagination.custom')) {
            Paginator::defaultView('vendor.pagination.custom');
        }
        if (View::exists('vendor.pagination.simple-custom')) {
            Paginator::defaultSimpleView('vendor.pagination.simple-custom');
        }

        // Custom S3 filesystem driver that omits S3 ACLs (unsupported by Supabase and by AWS buckets with ACLs disabled).
        // Registered as 'supabase-s3' (media) and 's3-noacl' (private backup destination; also applies Object Lock when configured).
        $s3NoAclBuilder = function ($app, array $config) {
            $s3Config = $config + ['version' => 'latest'];

            if (!empty($s3Config['key']) && !empty($s3Config['secret'])) {
                $s3Config['credentials'] = Arr::only($s3Config, ['key', 'secret']);
                if (!empty($s3Config['token'])) {
                    $s3Config['credentials']['token'] = $s3Config['token'];
                }
            }
            $s3Config = Arr::except($s3Config, ['token']);

            $root = (string) ($s3Config['root'] ?? '');
            $visibility = new PortableVisibilityConverter(
                $config['visibility'] ?? Visibility::PUBLIC
            );
            $streamReads = $s3Config['stream_reads'] ?? false;

            $client = $config['client'] ?? new S3Client($s3Config);

            if ($client instanceof AwsClientInterface) {
                // Strip ACL parameter from S3 commands to ensure compatibility with Supabase Storage
                $client->getHandlerList()->appendInit(function (callable $handler) {
                    return function (CommandInterface $command, ?RequestInterface $request = null) use ($handler) {
                        unset($command['ACL']);
                        return $handler($command, $request);
                    };
                }, 'supabase-strip-acl-param');

                // Strip x-amz-acl header from outgoing HTTP requests before SigV4 signing
                $client->getHandlerList()->appendBuild(function (callable $handler) {
                    return function (CommandInterface $command, ?RequestInterface $request = null) use ($handler) {
                        if ($request && $request->hasHeader('x-amz-acl')) {
                            $request = $request->withoutHeader('x-amz-acl');
                        }
                        return $handler($command, $request);
                    };
                }, 'supabase-strip-acl-header');

                // Optional Object Lock for backup packages: retain-until depends on the retention class tag
                // ("retention=daily|weekly|monthly") that BackupHistoryService::uploadOptions() sets.
                $objectLock = (array) ($config['object_lock'] ?? []);
                if (!empty($objectLock['mode'])) {
                    $client->getHandlerList()->appendInit(function (callable $handler) use ($objectLock) {
                        return function (CommandInterface $command, ?RequestInterface $request = null) use ($handler, $objectLock) {
                            if (in_array($command->getName(), ['PutObject', 'CreateMultipartUpload'], true)
                                && str_starts_with((string) ($command['Key'] ?? ''), 'packages/')) {
                                $class = 'daily';
                                if (preg_match('/retention=(daily|weekly|monthly)/', (string) ($command['Tagging'] ?? ''), $m)) {
                                    $class = $m[1];
                                }
                                $days = (int) (($objectLock['days'] ?? [])[$class] ?? 0);
                                if ($days > 0) {
                                    $command['ObjectLockMode'] = $objectLock['mode'];
                                    $command['ObjectLockRetainUntilDate'] = new \DateTimeImmutable('+' . $days . ' days');
                                }
                            }
                            return $handler($command, $request);
                        };
                    }, 'backup-object-lock');
                }
            }

            $adapter = new FlysystemS3Adapter(
                $client,
                $s3Config['bucket'],
                $root,
                $visibility,
                null,
                $config['options'] ?? [],
                $streamReads
            );

            $flysystem = new Filesystem($adapter, Arr::only($config, [
                'directory_visibility',
                'disable_asserts',
                'temporary_url',
                'url',
                'visibility',
            ]));

            return new LaravelS3Adapter($flysystem, $adapter, $s3Config, $client);
        };

        Storage::extend('supabase-s3', $s3NoAclBuilder);
        Storage::extend('s3-noacl', $s3NoAclBuilder);

        // Centralized RBAC Gate authorization
        Gate::before(function (\App\Models\User $user, string $ability) {
            return $user->hasPermission($ability) ?: null;
        });
        View::composer(['layouts.app', 'admin.settings', 'admin.notifications.*'], function ($view) {
            $notifications = collect();
            $unreadCount = 0;

            if (Auth::check()) {
                try {
                    // Throttled background sync (2-minute window) so condition alerts remain fresh
                    app(NotificationService::class)->syncThrottled();

                    $userId = Auth::id();
                    $unreadCount = Notification::forUser($userId)->unread()->count();
                    $notifications = Notification::forUser($userId)
                        ->orderBy('created_at', 'desc')
                        ->take(5)
                        ->get();
                } catch (\Throwable $e) {
                    // Silently fallback if table not yet migrated
                }
            }

            $view->with('realNotifications', $notifications);
            $view->with('realUnreadCount', $unreadCount);
        });
    }
}
