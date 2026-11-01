<?php

namespace App\Providers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
