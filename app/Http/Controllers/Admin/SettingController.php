<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display the System Settings view.
     */
    public function index(Request $request)
    {
        if ($request->get('tab') === 'notifications') {
            return app(NotificationController::class)->index($request);
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (!$user instanceof User || !$user->hasPermission('settings.view')) {
            abort(403, 'Unauthorized access to system settings.');
        }

        $settings = [
            'shop_name'                => SettingsService::getString('shop_name', 'Sweet Delights Bakery'),
            'shop_phone'               => SettingsService::getString('shop_phone', '+855 23 123 456'),
            'shop_email'               => SettingsService::getString('shop_email', 'info@sweetdelights.com'),
            'shop_address'             => SettingsService::getString('shop_address', 'Monivong Blvd, Phnom Penh, Cambodia'),
            'shop_logo'                => SettingsService::getString('shop_logo', ''),
            'tax_rate'                 => SettingsService::getString('tax_rate', '10.0'),
            'discount'                 => SettingsService::getString('discount', '5.0'),
            'receipt_header'           => SettingsService::getString('receipt_header', "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads"),
            'receipt_footer'           => SettingsService::getString('receipt_footer', "Thank you for visiting!\nFollow us @sweetdelights.bakery"),
            'currency'                 => SettingsService::getString('currency', 'USD'),
            'date_format'              => SettingsService::getString('date_format', 'DD/MM/YYYY'),
            'language'                 => SettingsService::getString('language', 'EN'),
            'theme_mode'               => SettingsService::getString('theme_mode', 'light'),
            'notifications_enabled'    => SettingsService::getString('notifications_enabled', '1'),
            'low_stock_alerts_enabled' => SettingsService::getString('low_stock_alerts_enabled', '1'),
            'expiry_alerts_enabled'    => SettingsService::getString('expiry_alerts_enabled', '1'),
            'opening_time'             => SettingsService::getString('opening_time', '07:00'),
            'closing_time'             => SettingsService::getString('closing_time', '20:00'),
            'low_stock_threshold'      => SettingsService::getString('low_stock_threshold', '5'),
            'expiry_warning_days'      => SettingsService::getString('expiry_warning_days', '3'),
        ];

        $canManage = $user->hasPermission('settings.manage');
        $isAdmin = $user->isAdmin();

        return view('admin.settings', compact('settings', 'canManage', 'isAdmin'));
    }

    /**
     * Update settings and configuration.
     */
    public function update(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(401);
        }

        // Normalize potential alias parameter names
        if ($request->filled('password') && !$request->filled('new_password')) {
            $request->merge(['new_password' => $request->input('password')]);
        }
        if ($request->filled('password_confirmation') && !$request->filled('new_password_confirmation')) {
            $request->merge(['new_password_confirmation' => $request->input('password_confirmation')]);
        }

        // 1. Optional Password update check (handles standalone or combined with profile update)
        $passwordUpdated = false;
        if ($request->filled('current_password') || $request->filled('new_password') || $request->filled('new_password_confirmation')) {
            $request->validate([
                'current_password'          => 'required|string',
                'new_password'              => 'required|string|min:6|confirmed',
                'new_password_confirmation' => 'required|string|min:6',
            ]);

            if (!Hash::check($request->current_password, $user->password)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Current password does not match.'], 422);
                }
                return redirect()->back()->withErrors(['current_password' => 'Current password does not match.']);
            }

            $user->password = Hash::make($request->new_password);
            $user->must_change_password = false;
            $user->save();
            $passwordUpdated = true;

            AuditLogService::log(
                'password_update',
                "User {$user->email} updated their account password via Settings.",
                $user
            );

            // If only password was submitted, return early with confirmation
            if (!$request->filled('shop_name') && !$request->filled('name') && !$request->filled('shop_phone') && !$request->filled('shop_email') && !$request->hasFile('shop_logo') && !$request->hasFile('avatar')) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'message' => 'Account password updated successfully!']);
                }
                return redirect()->back()->with('success', 'Account password updated successfully!');
            }
        }

        // Check general settings management permission
        if (!$user->hasPermission('settings.manage')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized: You do not have permission to modify system settings.'], 403);
            }
            abort(403, 'Unauthorized: You do not have permission to modify system settings.');
        }

        // 2. Validate configuration input (supports both Profile and Store branding)
        $validated = $request->validate([
            'name'                     => 'nullable|string|max:255',
            'shop_name'                => 'nullable|string|max:255',
            'phone'                    => 'nullable|string|max:50',
            'shop_phone'               => 'nullable|string|max:50',
            'email'                    => 'nullable|email|max:255',
            'shop_email'               => 'nullable|email|max:255',
            'shop_address'             => 'nullable|string|max:500',
            'tax_rate'                 => 'nullable|numeric|min:0|max:100',
            'discount'                 => 'nullable|numeric|min:0|max:100',
            'receipt_header'           => 'nullable|string|max:1000',
            'receipt_footer'           => 'nullable|string|max:1000',
            'currency'                 => 'nullable|string|in:USD,KHR,$,៛',
            'opening_time'             => 'nullable|date_format:H:i',
            'closing_time'             => 'nullable|date_format:H:i',
            'low_stock_threshold'      => 'nullable|numeric|min:0|max:10000',
            'expiry_warning_days'      => 'nullable|integer|min:1|max:90',
            'notifications_enabled'    => 'nullable|in:0,1',
            'low_stock_alerts_enabled' => 'nullable|in:0,1',
            'expiry_alerts_enabled'    => 'nullable|in:0,1',
            'date_format'              => 'nullable|string|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD',
            'language'                 => 'nullable|string|in:EN,KM,en,km',
            'theme_mode'               => 'nullable|string|in:light,dark',
            'shop_logo'                => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
            ],
            'avatar'                   => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
            ],
        ], [
            'shop_logo.image'     => 'The bakery logo must be a valid image file.',
            'shop_logo.mimes'     => 'The bakery logo must be a file of type: jpeg, png, jpg, webp.',
            'shop_logo.mimetypes' => 'The bakery logo must have a valid image MIME type (image/jpeg, image/png, image/webp).',
            'shop_logo.max'       => 'The bakery logo file size must not exceed 2MB.',
            'avatar.image'        => 'The avatar must be a valid image file.',
            'avatar.mimes'        => 'The avatar must be a file of type: jpeg, png, jpg, webp.',
            'avatar.mimetypes'    => 'The avatar must have a valid image MIME type (image/jpeg, image/png, image/webp).',
            'avatar.max'          => 'The avatar file size must not exceed 2MB.',
        ]);

        $uploadedLogoUrl = null;
        $uploadedAvatarUrl = null;
        $diskName = config('filesystems.settings_disk', config('filesystems.default') === 's3' ? 's3' : 'public');

        // Handle shop logo upload if provided
        if ($request->hasFile('shop_logo')) {
            $logoFile = $request->file('shop_logo');

            if (!$logoFile->isValid()) {
                $errorMsg = 'Upload failed: ' . $logoFile->getErrorMessage();
                if ($request->expectsJson()) {
                    return response()->json(['message' => $errorMsg, 'errors' => ['shop_logo' => [$errorMsg]]], 422);
                }
                return redirect()->back()->withInput()->withErrors(['shop_logo' => $errorMsg]);
            }

            // Verify binary image content
            $imageInfo = @getimagesize($logoFile->getRealPath());
            if ($imageInfo === false) {
                $errorMsg = 'The uploaded file does not contain valid image content.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $errorMsg, 'errors' => ['shop_logo' => [$errorMsg]]], 422);
                }
                return redirect()->back()->withInput()->withErrors(['shop_logo' => $errorMsg]);
            }

            $rawExt = $logoFile->guessExtension() ?: $logoFile->getClientOriginalExtension();
            $extension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $rawExt)) ?: 'png';
            $logoFilename = 'bakery_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

            // Preserve old logo reference until new upload successfully completes
            $previousLogo = SettingsService::getString('shop_logo', '');

            try {
                $disk = Storage::disk($diskName);
                $storedPath = $disk->putFileAs('logos', $logoFile, $logoFilename);

                if (!$storedPath || !$disk->exists('logos/' . $logoFilename)) {
                    throw new \RuntimeException('Persistent storage disk was unable to write the logo file.');
                }

                $logoPath = ($diskName === 'public')
                    ? '/storage/logos/' . $logoFilename
                    : $disk->url($storedPath);

                // Ensure Supabase S3 URLs use the public object URL format instead of raw S3 API endpoint
                if (str_contains($logoPath, '.storage.supabase.co/storage/v1/s3/')) {
                    $logoPath = str_replace('.storage.supabase.co/storage/v1/s3/', '.supabase.co/storage/v1/object/public/', $logoPath);
                }

                SettingsService::set('shop_logo', $logoPath);
                SettingsService::set('user_avatar_' . $user->id, $logoPath);
                SettingsService::set('admin_avatar', $logoPath);
                $uploadedLogoUrl = $logoPath;

                // Safely delete previous logo only AFTER the new logo has succeeded
                if (!empty($previousLogo) && $previousLogo !== $logoPath) {
                    try {
                        $parsedPath = parse_url($previousLogo, PHP_URL_PATH) ?? $previousLogo;
                        $pos = strpos($parsedPath, 'logos/');
                        if ($pos !== false) {
                            $oldKey = substr($parsedPath, $pos);
                            if ($disk->exists($oldKey)) {
                                $disk->delete($oldKey);
                            }
                            if ($diskName !== 'public' && Storage::disk('public')->exists($oldKey)) {
                                Storage::disk('public')->delete($oldKey);
                            }
                        }
                    } catch (\Throwable $delEx) {
                        Log::warning("Failed to remove previous logo [{$previousLogo}]: " . $delEx->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Bakery logo upload failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'user_id'   => $user->id,
                ]);

                $userMessage = 'Failed to store bakery logo. Please verify storage permissions or disk availability.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $userMessage,
                        'errors'  => ['shop_logo' => [$userMessage]],
                    ], 422);
                }
                return redirect()->back()->withInput()->withErrors(['shop_logo' => $userMessage]);
            }
        }

        // Handle profile avatar upload if provided
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');

            if (!$avatarFile->isValid()) {
                $errorMsg = 'Avatar upload failed: ' . $avatarFile->getErrorMessage();
                if ($request->expectsJson()) {
                    return response()->json(['message' => $errorMsg, 'errors' => ['avatar' => [$errorMsg]]], 422);
                }
                return redirect()->back()->withInput()->withErrors(['avatar' => $errorMsg]);
            }

            // Verify binary image content
            $imageInfo = @getimagesize($avatarFile->getRealPath());
            if ($imageInfo === false) {
                $errorMsg = 'The uploaded avatar does not contain valid image content.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $errorMsg, 'errors' => ['avatar' => [$errorMsg]]], 422);
                }
                return redirect()->back()->withInput()->withErrors(['avatar' => $errorMsg]);
            }

            $rawExt = $avatarFile->guessExtension() ?: $avatarFile->getClientOriginalExtension();
            $extension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $rawExt)) ?: 'png';
            $avatarFilename = 'avatar_' . $user->id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

            $previousAvatar = SettingsService::getString('user_avatar_' . $user->id, '');

            try {
                $disk = Storage::disk($diskName);
                $storedPath = $disk->putFileAs('avatars', $avatarFile, $avatarFilename);

                if (!$storedPath || !$disk->exists('avatars/' . $avatarFilename)) {
                    throw new \RuntimeException('Persistent storage disk was unable to write the avatar file.');
                }

                $avatarPath = ($diskName === 'public')
                    ? '/storage/avatars/' . $avatarFilename
                    : $disk->url($storedPath);

                // Ensure Supabase S3 URLs use the public object URL format instead of raw S3 API endpoint
                if (str_contains($avatarPath, '.storage.supabase.co/storage/v1/s3/')) {
                    $avatarPath = str_replace('.storage.supabase.co/storage/v1/s3/', '.supabase.co/storage/v1/object/public/', $avatarPath);
                }

                SettingsService::set('user_avatar_' . $user->id, $avatarPath);
                SettingsService::set('admin_avatar', $avatarPath);
                $uploadedAvatarUrl = $avatarPath;

                if (!empty($previousAvatar) && $previousAvatar !== $avatarPath) {
                    try {
                        $parsedPath = parse_url($previousAvatar, PHP_URL_PATH) ?? $previousAvatar;
                        $pos = strpos($parsedPath, 'avatars/');
                        if ($pos !== false) {
                            $oldKey = substr($parsedPath, $pos);
                            if ($disk->exists($oldKey)) {
                                $disk->delete($oldKey);
                            }
                            if ($diskName !== 'public' && Storage::disk('public')->exists($oldKey)) {
                                Storage::disk('public')->delete($oldKey);
                            }
                        }
                    } catch (\Throwable $delEx) {
                        Log::warning("Failed to remove previous avatar [{$previousAvatar}]: " . $delEx->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Avatar upload failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'user_id'   => $user->id,
                ]);

                $userMessage = 'Failed to store avatar image. Please verify storage permissions or disk availability.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $userMessage,
                        'errors'  => ['avatar' => [$userMessage]],
                    ], 422);
                }
                return redirect()->back()->withInput()->withErrors(['avatar' => $userMessage]);
            }
        }

        $fieldsToUpdate = [
            'shop_name', 'shop_phone', 'shop_email', 'shop_address',
            'tax_rate', 'discount', 'receipt_header', 'receipt_footer',
            'currency', 'date_format', 'language', 'theme_mode',
            'notifications_enabled', 'low_stock_alerts_enabled', 'expiry_alerts_enabled',
            'opening_time', 'closing_time', 'low_stock_threshold', 'expiry_warning_days'
        ];

        $updatedKeys = [];
        foreach ($fieldsToUpdate as $field) {
            if ($request->has($field)) {
                $val = $request->input($field);
                SettingsService::set($field, $val);
                $updatedKeys[$field] = $val;
            }
        }

        // Synchronize authenticated user profile fields
        $userUpdated = false;
        if ($request->filled('name')) {
            $user->name = $request->input('name');
            $userUpdated = true;
        }
        if ($request->filled('email')) {
            $user->email = $request->input('email');
            $userUpdated = true;
        } elseif ($request->filled('shop_email')) {
            $user->email = $request->input('shop_email');
            $userUpdated = true;
        }
        if ($request->filled('phone')) {
            $user->phone = $request->input('phone');
            $userUpdated = true;
        } elseif ($request->filled('shop_phone')) {
            $user->phone = $request->input('shop_phone');
            $userUpdated = true;
        }
        if ($userUpdated) {
            $user->save();
        }

        if ($request->filled('language')) {
            $lang = strtolower($request->language);
            Session::put('locale', $lang);
            cookie()->queue(cookie()->forever('locale', $lang));
            app()->setLocale($lang);
        }

        AuditLogService::log(
            'settings_update',
            "System settings updated by {$user->name} ({$user->role}).",
            null,
            ['updated_fields' => array_keys($updatedKeys)]
        );

        if ($request->expectsJson()) {
            $successMsg = $passwordUpdated 
                ? 'Profile, password, and settings saved successfully!' 
                : 'Profile and store settings saved successfully!';
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'settings' => $updatedKeys,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_phone' => $user->phone,
                'avatar_url' => $uploadedAvatarUrl ?? $uploadedLogoUrl,
                'logo_url' => $uploadedLogoUrl ?? $uploadedAvatarUrl,
            ]);
        }

        return redirect()->back()->with('success', 'System settings saved successfully!');
    }

    /**
     * Generate and download a safe system configuration and catalog backup.
     */
    public function createBackup(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user instanceof User || !$user->isAdmin()) {
            abort(403, 'Unauthorized: Only system administrators can generate backups.');
        }

        $allSettings = Setting::query()->pluck('value', 'key')->toArray();
        $categories = Category::query()->select('id', 'name', 'description', 'status')->get()->toArray();
        $products = Product::query()->select('id', 'name', 'sku', 'price', 'cost', 'stock', 'status')->get()->toArray();

        $backupPayload = [
            'system'          => 'Bakery Management System',
            'version'         => '1.0',
            'created_at'      => now()->toIso8601String(),
            'generated_by'    => $user->name . ' (' . $user->email . ')',
            'settings'        => $allSettings,
            'categories'      => $categories,
            'products'        => $products,
        ];

        $filename = 'bakery_backup_' . date('Y_m_d_His') . '.json';
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filepath, json_encode($backupPayload, JSON_PRETTY_PRINT));

        AuditLogService::log(
            'backup_created',
            "Administrator {$user->name} created and downloaded system backup {$filename}.",
            null,
            ['filename' => $filename, 'file_size' => filesize($filepath)]
        );

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Restore system configuration from an authenticated, validated backup file.
     */
    public function restoreBackup(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user instanceof User || !$user->isAdmin()) {
            abort(403, 'Unauthorized: Only system administrators can restore backups.');
        }

        $request->validate([
            'backup_file'     => 'required|file|max:5120',
            'confirm_restore' => 'required',
        ], [
            'confirm_restore.required' => 'You must check the confirmation box to proceed with restoring settings.',
        ]);

        $file = $request->file('backup_file');
        $rawContent = file_get_contents($file->getRealPath());
        $payload = json_decode($rawContent, true);

        if (!is_array($payload) || !isset($payload['settings']) || !is_array($payload['settings'])) {
            return redirect()->back()->withErrors([
                'backup_file' => 'The uploaded file is not a valid system backup or contains corrupted data.'
            ]);
        }

        try {
            DB::transaction(function () use ($payload) {
                SettingsService::setMany($payload['settings']);
            });

            AuditLogService::log(
                'backup_restored',
                "Administrator {$user->name} restored system settings from backup file.",
                null,
                ['settings_count' => count($payload['settings'])]
            );

            return redirect()->back()->with('success', 'System settings restored successfully from backup!');
        } catch (\Throwable $e) {
            AuditLogService::log(
                'backup_restore_failed',
                "Restore attempt failed: " . $e->getMessage(),
                null
            );

            return redirect()->back()->withErrors([
                'backup_file' => 'Failed to restore configuration: ' . $e->getMessage()
            ]);
        }
    }
}
