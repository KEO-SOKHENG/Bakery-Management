<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SettingsService;
use Aws\MockHandler;
use Aws\Result;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class SettingManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $cashier;
    private User $baker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'     => 'Admin User',
            'email'    => 'admin@bakery.com',
            'username' => 'admin',
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        $this->manager = User::factory()->create([
            'name'     => 'Manager User',
            'email'    => 'manager@bakery.com',
            'username' => 'manager',
            'role'     => 'manager',
            'status'   => 'active',
        ]);

        $this->cashier = User::factory()->create([
            'name'     => 'Cashier User',
            'email'    => 'cashier@bakery.com',
            'username' => 'cashier',
            'role'     => 'cashier',
            'status'   => 'active',
        ]);

        $this->baker = User::factory()->create([
            'name'     => 'Baker User',
            'email'    => 'baker@bakery.com',
            'username' => 'baker',
            'role'     => 'baker',
            'status'   => 'active',
        ]);

        config([
            'filesystems.default'       => 'local',
            'filesystems.settings_disk' => 'public',
        ]);

        SettingsService::clearCache();
    }

    /**
     * Get a filesystem disk instance typed as FilesystemAdapter for IDE and static analysis.
     */
    protected function storageDisk(string $name = 'public'): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($name);

        return $disk;
    }

    /**
     * 1. Admin can access Settings.
     */
    public function test_admin_can_access_settings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.settings');
        $response->assertSee('System Settings');
        $response->assertSee('Shop Information');
        $response->assertSee('Tax & Discount', false);
        $response->assertSee('Business Hours & Operations', false);
        $response->assertSee('System Backup & Restore', false);
    }

    /**
     * 2. Unauthorized users cannot modify Settings (Cashier, Baker).
     */
    public function test_unauthorized_users_cannot_modify_settings(): void
    {
        // Web request: redirected to user's dashboard
        $response = $this->actingAs($this->cashier)->get(route('admin.settings'));
        $response->assertRedirect(route('cashier.dashboard'));

        $postResponse = $this->actingAs($this->cashier)->post(route('admin.settings.update'), [
            'shop_name' => 'Hacked Bakery',
        ]);
        $postResponse->assertRedirect(route('cashier.dashboard'));

        // JSON/AJAX request: returns 403 Forbidden
        $ajaxResponse = $this->actingAs($this->baker)->json('POST', route('admin.settings.update'), [
            'shop_name' => 'Hacked Bakery',
        ]);
        $ajaxResponse->assertStatus(403);
    }

    /**
     * 3. Manager without permission cannot modify settings.
     */
    public function test_manager_without_manage_permission_cannot_modify_settings(): void
    {
        // Manager by default has settings.view but not settings.manage
        $response = $this->actingAs($this->manager)->get(route('admin.settings'));
        $response->assertStatus(200);
        $response->assertSee('View-only mode');

        $updateResponse = $this->actingAs($this->manager)->json('POST', route('admin.settings.update'), [
            'tax_rate' => 15.0,
        ]);
        $updateResponse->assertStatus(403);
    }

    /**
     * 4. Admin can update bakery information.
     */
    public function test_admin_can_update_bakery_information(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_name'    => 'Artisan Golden Loaf',
            'shop_phone'   => '+855 99 888 777',
            'shop_email'   => 'contact@goldenloaf.com',
            'shop_address' => 'Street 240, Phnom Penh, Cambodia',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('Artisan Golden Loaf', SettingsService::get('shop_name'));
        $this->assertEquals('Artisan Golden Loaf', SettingsService::get('bakery_name'));
        $this->assertEquals('contact@goldenloaf.com', SettingsService::get('shop_email'));
    }

    /**
     * 5. Admin can update tax rate.
     */
    public function test_admin_can_update_tax_rate(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'tax_rate' => 12.5,
            'discount' => 8.0,
        ]);

        $response->assertRedirect();
        $this->assertEquals(12.5, SettingsService::getFloat('tax_rate'));
        $this->assertEquals(12.5, SettingsService::getFloat('tax_percentage'));
    }

    /**
     * 6. Admin can update currency.
     */
    public function test_admin_can_update_currency(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'currency' => 'KHR',
        ]);

        $response->assertRedirect();
        $this->assertEquals('KHR', SettingsService::getString('currency'));
        $this->assertEquals('៛', SettingsService::getCurrencySymbol());

        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'currency' => 'USD',
        ]);
        $this->assertEquals('$', SettingsService::getCurrencySymbol());
    }

    /**
     * 7. Admin can update business hours.
     */
    public function test_admin_can_update_business_hours(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'opening_time' => '06:30',
            'closing_time' => '21:30',
        ]);

        $response->assertRedirect();
        $this->assertEquals('06:30', SettingsService::getString('opening_time'));
        $this->assertEquals('21:30', SettingsService::getString('closing_time'));
    }

    /**
     * 8. Invalid tax rate is rejected.
     */
    public function test_invalid_tax_rate_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'tax_rate' => -5.0, // Negative tax
        ]);

        $response->assertSessionHasErrors('tax_rate');

        $response2 = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'tax_rate' => 150.0, // Exceeds 100%
        ]);

        $response2->assertSessionHasErrors('tax_rate');
    }

    /**
     * 9. Invalid currency is rejected.
     */
    public function test_invalid_currency_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'currency' => 'BITCOIN', // Invalid currency
        ]);

        $response->assertSessionHasErrors('currency');
    }

    /**
     * 10. Invalid time is rejected.
     */
    public function test_invalid_time_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'opening_time' => '25:99', // Invalid time format
        ]);

        $response->assertSessionHasErrors('opening_time');
    }

    /**
     * 11. Settings persist correctly in database.
     */
    public function test_settings_persist_correctly_in_database(): void
    {
        SettingsService::set('shop_name', 'Baguette Haven');

        $this->assertDatabaseHas('settings', [
            'key'   => 'shop_name',
            'value' => 'Baguette Haven',
        ]);

        // Verify alias sync in database
        $this->assertDatabaseHas('settings', [
            'key'   => 'bakery_name',
            'value' => 'Baguette Haven',
        ]);
    }

    /**
     * 12. Settings service returns correct typed values.
     */
    public function test_settings_service_returns_correct_typed_values(): void
    {
        SettingsService::set('tax_rate', '12.5');
        SettingsService::set('expiry_warning_days', '5');
        SettingsService::set('notifications_enabled', '1');

        $this->assertSame(12.5, SettingsService::getFloat('tax_rate'));
        $this->assertSame(5, SettingsService::getInt('expiry_warning_days'));
        $this->assertTrue(SettingsService::getBool('notifications_enabled'));
        $this->assertSame('12.5', SettingsService::getString('tax_rate'));
    }

    /**
     * 13. Cache is refreshed/invalidated after update.
     */
    public function test_cache_is_refreshed_after_update(): void
    {
        SettingsService::set('shop_name', 'Initial Bakery');
        $this->assertEquals('Initial Bakery', SettingsService::get('shop_name'));

        // Update via controller
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_name' => 'Updated Cached Bakery',
        ]);

        // Immediate subsequent call must return fresh value without reboot
        $this->assertEquals('Updated Cached Bakery', SettingsService::get('shop_name'));
    }

    /**
     * 14. POS uses configured tax rate.
     */
    public function test_pos_uses_configured_tax_rate(): void
    {
        SettingsService::set('tax_percentage', '15.0');

        $cat = Category::create(['name' => 'Breads', 'slug' => 'breads', 'status' => 'active']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'French Baguette',
            'slug'        => 'french-baguette',
            'price'       => 10.00,
            'cost'        => 3.00,
            'stock'       => 50,
            'status'      => 'active',
        ]);

        $response = $this->actingAs($this->cashier)->json('POST', route('pos.checkout'), [
            'payment_method' => 'cash',
            'items'          => [
                ['product_id' => $product->id, 'quantity' => 2], // subtotal = 20.00
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        // 20.00 * 15% = 3.00 tax -> total = 23.00
        $this->assertEquals('3.00', $data['receipt']['tax']);
        $this->assertEquals('23.00', $data['receipt']['grand_total']);
    }

    /**
     * 15. POS uses configured currency.
     */
    public function test_pos_uses_configured_currency(): void
    {
        SettingsService::set('currency', 'KHR');

        $response = $this->actingAs($this->cashier)->get(route('pos'));
        $response->assertStatus(200);
        $response->assertViewHas('currency', '៛');
    }

    /**
     * 16. Notification expiry threshold uses configured setting.
     */
    public function test_notification_expiry_threshold_uses_configured_setting(): void
    {
        // Set expiry warning window to 5 days
        SettingsService::set('expiry_warning_days', '5');

        $ingredient = Ingredient::create([
            'name'             => 'Fresh Cream',
            'unit'             => 'liters',
            'quantity'         => 10,
            'minimum_quantity' => 2,
            'cost'             => 4.00,
            'expiry_date'      => Carbon::today()->addDays(4)->format('Y-m-d'), // within 5 days
        ]);

        $service = app(NotificationService::class);
        $counts = $service->syncConditionAlerts();

        $this->assertGreaterThanOrEqual(1, $counts['expiring']);
        $this->assertDatabaseHas('notifications', [
            'type'      => 'expiring',
            'dedup_key' => "expiring_5days:ingredient:{$ingredient->id}",
        ]);
    }

    /**
     * 17. Notification settings do not create duplicate alerts.
     */
    public function test_notification_settings_do_not_create_duplicate_alerts(): void
    {
        SettingsService::set('expiry_warning_days', '3');

        $ingredient = Ingredient::create([
            'name'             => 'Butter',
            'unit'             => 'kg',
            'quantity'         => 15,
            'minimum_quantity' => 2,
            'cost'             => 5.00,
            'expiry_date'      => Carbon::today()->addDays(2)->format('Y-m-d'),
        ]);

        $service = app(NotificationService::class);

        // Run sync multiple times
        $service->syncConditionAlerts();
        $service->syncConditionAlerts();
        $service->syncConditionAlerts();

        $alertCount = Notification::where('dedup_key', "expiring_3days:ingredient:{$ingredient->id}")->count();
        $targetUserCount = User::whereIn('role', ['admin', 'manager', 'baker'])->count();

        $this->assertEquals($targetUserCount, $alertCount);
    }

    /**
     * 18. Historical sales are not recalculated when current tax changes.
     */
    public function test_historical_sales_are_not_recalculated_when_tax_changes(): void
    {
        // 1. Create order at 10% tax
        SettingsService::set('tax_percentage', '10.0');

        $cat = Category::create(['name' => 'Pastries', 'slug' => 'pastries', 'status' => 'active']);
        $prod = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Croissant',
            'slug'        => 'croissant',
            'price'       => 10.00,
            'cost'        => 2.00,
            'stock'       => 20,
            'status'      => 'active',
        ]);

        $this->actingAs($this->cashier)->json('POST', route('pos.checkout'), [
            'payment_method' => 'cash',
            'items'          => [['product_id' => $prod->id, 'quantity' => 1]],
        ]);

        $order = Order::latest()->first();
        $this->assertEquals(1.00, (float) $order->tax);
        $this->assertEquals(11.00, (float) $order->total);

        // 2. Change tax rate to 20%
        SettingsService::set('tax_percentage', '20.0');

        // Verify historical order records remain unchanged
        $order->refresh();
        $this->assertEquals(1.00, (float) $order->tax);
        $this->assertEquals(11.00, (float) $order->total);
    }

    /**
     * 19. Audit logs are created for setting changes.
     */
    public function test_audit_logs_are_created_for_setting_changes(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_name' => 'Audited Bakery Name',
            'tax_rate'  => 8.5,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action'  => 'settings_update',
        ]);
    }

    /**
     * 20. Unauthorized backup/restore is blocked.
     */
    public function test_unauthorized_backup_and_restore_is_blocked(): void
    {
        // Manager blocked
        $resManager = $this->actingAs($this->manager)->post(route('admin.settings.backup'));
        $resManager->assertRedirect(); // CheckRole redirects non-admin

        // Cashier blocked
        $resCashier = $this->actingAs($this->cashier)->post(route('admin.settings.backup'));
        $resCashier->assertRedirect();

        // Cashier JSON blocked
        $resJson = $this->actingAs($this->cashier)->json('POST', route('admin.settings.backup'));
        $resJson->assertStatus(403);
    }

    /**
     * 21. Backup behavior works and safe restore succeeds.
     */
    public function test_backup_generation_and_safe_restore_works(): void
    {
        // 1. Admin generates backup
        $backupResponse = $this->actingAs($this->admin)->post(route('admin.settings.backup'));
        $backupResponse->assertStatus(200);
        $backupResponse->assertHeader('content-type', 'application/json');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action'  => 'backup_created',
        ]);

        // 2. Prepare verified backup file payload
        $restorePayload = [
            'system'   => 'Bakery Management System',
            'version'  => '1.0',
            'settings' => [
                'shop_name'           => 'Restored Heritage Bakery',
                'currency'            => 'KHR',
                'tax_rate'            => '7.5',
                'expiry_warning_days' => '4',
            ],
        ];

        $uploadedFile = UploadedFile::fake()->createWithContent(
            'restore_test.json',
            json_encode($restorePayload)
        );

        // 3. Restore without confirmation checkbox is rejected
        $failedResponse = $this->actingAs($this->admin)->post(route('admin.settings.restore'), [
            'backup_file' => $uploadedFile,
        ]);
        $failedResponse->assertSessionHasErrors('confirm_restore');

        // 4. Restore with confirmation succeeds
        $successResponse = $this->actingAs($this->admin)->post(route('admin.settings.restore'), [
            'backup_file'     => $uploadedFile,
            'confirm_restore' => '1',
        ]);

        $successResponse->assertRedirect();
        $successResponse->assertSessionHas('success');

        $this->assertEquals('Restored Heritage Bakery', SettingsService::get('shop_name'));
        $this->assertEquals('KHR', SettingsService::get('currency'));
        $this->assertEquals(7.5, SettingsService::getFloat('tax_rate'));
        $this->assertEquals(4, SettingsService::getInt('expiry_warning_days'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action'  => 'backup_restored',
        ]);
    }

    /**
     * 22. Admin can update settings without uploading a logo.
     */
    public function test_admin_can_update_settings_without_uploading_logo(): void
    {
        SettingsService::set('shop_logo', '/storage/logos/existing_logo.png');

        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_name'  => 'No Logo Bakery',
            'shop_phone' => '+855 12 345 678',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('No Logo Bakery', SettingsService::get('shop_name'));
        $this->assertEquals('/storage/logos/existing_logo.png', SettingsService::get('shop_logo'));
    }

    /**
     * 23. Admin can upload a valid bakery logo using Storage.
     */
    public function test_admin_can_upload_valid_bakery_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('my_bakery_logo.png', 400, 400);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_name' => 'Moon Cake Bakery',
            'shop_logo' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $storedLogo = SettingsService::get('shop_logo');
        $this->assertNotEmpty($storedLogo);
        $this->assertStringStartsWith('/storage/logos/bakery_logo_', $storedLogo);
        $this->assertStringEndsWith('.png', $storedLogo);

        $relativeDiskPath = str_replace('/storage/', '', $storedLogo);
        $this->storageDisk('public')->assertExists($relativeDiskPath);
    }

    /**
     * 24. Uploading logo via JSON/AJAX returns valid JSON with logo_url.
     */
    public function test_admin_can_upload_logo_via_ajax_and_receive_json(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('ajax_logo.jpg', 300, 300);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'shop_name' => 'AJAX Bakery',
            'shop_logo' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertNotNull($data['logo_url']);
        $this->assertStringStartsWith('/storage/logos/bakery_logo_', $data['logo_url']);
    }

    /**
     * 25. Reject invalid logo file type (e.g. PDF or non-image).
     */
    public function test_reject_invalid_logo_file_type(): void
    {
        Storage::fake('public');

        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $invalidFile,
        ]);

        $response->assertSessionHasErrors('shop_logo');
    }

    /**
     * 26. Reject fake image file (corrupted or spoofed non-image content).
     */
    public function test_reject_fake_image_content(): void
    {
        Storage::fake('public');

        // Text content disguised with a .png extension
        $fakeImage = UploadedFile::fake()->createWithContent('fake_logo.png', 'NOT_AN_IMAGE_BINARY_DATA');

        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $fakeImage,
        ]);

        $response->assertSessionHasErrors('shop_logo');
    }

    /**
     * 27. Saved logo is displayed in Settings view after page refresh.
     */
    public function test_saved_logo_is_displayed_after_page_refresh(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('fresh_logo.webp', 250, 250);

        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $file,
        ]);

        $storedLogo = SettingsService::get('shop_logo');

        // Fetch settings page as if refreshing or loading in browser
        $viewResponse = $this->actingAs($this->admin)->get(route('admin.settings'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee(asset($storedLogo), false);
    }

    /**
     * 28. Replacing existing logo cleans up previous storage file only after successful upload.
     */
    public function test_replacing_existing_logo_cleans_up_previous_file_only_after_success(): void
    {
        Storage::fake('public');

        // 1. First upload
        $firstFile = UploadedFile::fake()->image('first_logo.png', 200, 200);
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $firstFile,
        ]);

        $firstLogo = SettingsService::get('shop_logo');
        $firstDiskPath = str_replace('/storage/', '', $firstLogo);
        $this->storageDisk('public')->assertExists($firstDiskPath);

        // 2. Second upload (replaces first)
        $secondFile = UploadedFile::fake()->image('second_logo.jpg', 200, 200);
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $secondFile,
        ]);

        $secondLogo = SettingsService::get('shop_logo');
        $secondDiskPath = str_replace('/storage/', '', $secondLogo);

        // Second logo must exist
        $this->storageDisk('public')->assertExists($secondDiskPath);
        // First logo must have been cleaned up
        $this->storageDisk('public')->assertMissing($firstDiskPath);
        $this->assertNotEquals($firstLogo, $secondLogo);
    }

    /**
     * 29. Previous logo is preserved when a new upload fails validation or storage.
     */
    public function test_previous_logo_is_preserved_when_new_upload_fails(): void
    {
        config(['filesystems.settings_disk' => 'public']);
        Storage::fake('public');

        // 1. Initial valid upload
        $validFile = UploadedFile::fake()->image('good_logo.png', 200, 200);
        $initResponse = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $validFile,
        ]);
        $initResponse->assertRedirect();
        $initResponse->assertSessionHas('success');

        $originalLogo = SettingsService::get('shop_logo');
        $this->assertNotEmpty($originalLogo);
        $originalDiskPath = str_replace('/storage/', '', $originalLogo);
        $this->storageDisk('public')->assertExists($originalDiskPath);

        // 2. Attempt uploading an invalid file (validation failure)
        $invalidFile = UploadedFile::fake()->create('hacker.exe', 500, 'application/octet-stream');
        $failResponse = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $invalidFile,
        ]);

        $failResponse->assertSessionHasErrors('shop_logo');

        // 3. Verify original logo is still preserved in DB and on disk
        $this->assertEquals($originalLogo, SettingsService::get('shop_logo'));
        $this->storageDisk('public')->assertExists($originalDiskPath);

        // 4. Attempt uploading a valid file when storage disk fails (storage failure)
        /** @var \Illuminate\Contracts\Filesystem\Filesystem&MockInterface $mockDisk */
        $mockDisk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('putFileAs')
            ->andThrow(new \RuntimeException('Storage disk unavailable'));
        $mockDisk->shouldReceive('exists')->andReturn(false);

        Storage::set('public', $mockDisk);

        $secondValidFile = UploadedFile::fake()->image('second_good_logo.png', 200, 200);
        $storageFailResponse = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $secondValidFile,
        ]);

        $storageFailResponse->assertSessionHasErrors('shop_logo');

        // 5. Verify original logo in database is STILL preserved after storage failure
        $this->assertEquals($originalLogo, SettingsService::get('shop_logo'));
    }

    /**
     * 30. Storage unavailable returns graceful error message instead of HTTP 500.
     */
    public function test_storage_unavailable_returns_graceful_error_instead_of_http_500(): void
    {
        // Mock Storage disk to throw a Filesystem exception when writing
        /** @var \Illuminate\Contracts\Filesystem\Filesystem&MockInterface $mockDisk */
        $mockDisk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('putFileAs')
            ->andThrow(new \RuntimeException('Disk write failure: permission denied'));
        $mockDisk->shouldReceive('exists')->andReturn(false);

        Storage::set('public', $mockDisk);

        $file = UploadedFile::fake()->image('broken_disk.png', 200, 200);

        // Web request: redirected back with error, not HTTP 500
        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('shop_logo');

        // AJAX request: returns 422 with useful message, not HTTP 500
        $ajaxResponse = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'shop_logo' => $file,
        ]);

        $ajaxResponse->assertStatus(422);
        $ajaxResponse->assertJsonStructure(['message', 'errors' => ['shop_logo']]);
    }

    /**
     * 31. Reject oversized logo file (exceeds 2MB limit).
     */
    public function test_reject_oversized_logo_file(): void
    {
        Storage::fake('public');

        // Create an image file exceeding 2048 KB (e.g. 3000 KB)
        $oversizedFile = UploadedFile::fake()->image('huge_logo.png')->size(3000);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $oversizedFile,
        ]);

        $response->assertSessionHasErrors('shop_logo');
    }

    /**
     * 32. Admin can upload a valid profile avatar using Storage.
     */
    public function test_admin_can_upload_valid_avatar_using_storage(): void
    {
        Storage::fake('public');

        $avatarFile = UploadedFile::fake()->image('admin_avatar.png', 300, 300);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'avatar' => $avatarFile,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertNotNull($data['avatar_url']);
        $this->assertStringStartsWith('/storage/avatars/avatar_', $data['avatar_url']);

        $relativeDiskPath = str_replace('/storage/', '', $data['avatar_url']);
        $this->storageDisk('public')->assertExists($relativeDiskPath);
    }

    /**
     * 33. Supabase S3 disk configuration resolves correct public object URL format.
     */
    public function test_supabase_s3_disk_configuration_resolves_correct_public_object_url(): void
    {
        config([
            'filesystems.disks.s3.key' => 'mock_key',
            'filesystems.disks.s3.secret' => 'mock_secret',
            'filesystems.disks.s3.region' => 'ap-northeast-1',
            'filesystems.disks.s3.bucket' => 'bakery-media',
            'filesystems.disks.s3.endpoint' => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
            'filesystems.disks.s3.url' => 'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media',
        ]);

        $url = $this->storageDisk('s3')->url('logos/bakery_logo_1730000000.png');
        $this->assertEquals(
            'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/logos/bakery_logo_1730000000.png',
            $url
        );
    }

    /**
     * 34. Admin can upload logo to Supabase S3 disk and receive public object URL.
     */
    public function test_admin_can_upload_logo_to_supabase_s3_disk(): void
    {
        config(['filesystems.settings_disk' => 's3']);
        Storage::fake('s3');

        $file = UploadedFile::fake()->image('supabase_logo.png', 400, 400);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'shop_name' => 'Moon Cake Supabase Bakery',
            'shop_logo' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $storedLogo = SettingsService::get('shop_logo');
        $this->assertNotEmpty($storedLogo);

        // Verify the file was stored on the fake S3 disk
        $parsedPath = parse_url($storedLogo, PHP_URL_PATH) ?? $storedLogo;
        $pos = strpos($parsedPath, 'logos/');
        $this->assertNotFalse($pos);
        $objectKey = substr($parsedPath, $pos);
        $this->storageDisk('s3')->assertExists($objectKey);
    }

    /**
     * 35. Admin can upload avatar to Supabase S3 disk.
     */
    public function test_admin_can_upload_avatar_to_supabase_s3_disk(): void
    {
        config(['filesystems.settings_disk' => 's3']);
        Storage::fake('s3');

        $file = UploadedFile::fake()->image('supabase_avatar.png', 300, 300);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'avatar' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $storedAvatar = SettingsService::get('user_avatar_' . $this->admin->id);
        $this->assertNotEmpty($storedAvatar);

        $parsedPath = parse_url($storedAvatar, PHP_URL_PATH) ?? $storedAvatar;
        $pos = strpos($parsedPath, 'avatars/');
        $this->assertNotFalse($pos);
        $objectKey = substr($parsedPath, $pos);
        $this->storageDisk('s3')->assertExists($objectKey);
    }

    /**
     * 36. Replacing logo on Supabase S3 cleans up previous file only after new upload succeeds.
     */
    public function test_replacing_logo_on_supabase_s3_cleans_up_previous_file_only_after_success(): void
    {
        config(['filesystems.settings_disk' => 's3']);
        Storage::fake('s3');

        // 1. Initial upload
        $firstFile = UploadedFile::fake()->image('first_s3.png', 200, 200);
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $firstFile,
        ]);

        $firstLogo = SettingsService::get('shop_logo');
        $firstParsed = parse_url($firstLogo, PHP_URL_PATH) ?? $firstLogo;
        $firstKey = substr($firstParsed, strpos($firstParsed, 'logos/'));
        $this->storageDisk('s3')->assertExists($firstKey);

        // 2. Second upload
        $secondFile = UploadedFile::fake()->image('second_s3.png', 200, 200);
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'shop_logo' => $secondFile,
        ]);

        $secondLogo = SettingsService::get('shop_logo');
        $secondParsed = parse_url($secondLogo, PHP_URL_PATH) ?? $secondLogo;
        $secondKey = substr($secondParsed, strpos($secondParsed, 'logos/'));

        $this->storageDisk('s3')->assertExists($secondKey);
        $this->storageDisk('s3')->assertMissing($firstKey);
        $this->assertNotEquals($firstLogo, $secondLogo);
    }

    /**
     * 37. Supabase S3 storage failure is handled gracefully without returning HTTP 500 or exposing credentials.
     */
    public function test_supabase_s3_storage_failure_handled_gracefully(): void
    {
        config(['filesystems.settings_disk' => 's3']);

        /** @var \Illuminate\Contracts\Filesystem\Filesystem&MockInterface $mockS3Disk */
        $mockS3Disk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockS3Disk->shouldReceive('putFileAs')
            ->andThrow(new \RuntimeException('Supabase S3 connection timeout error'));
        $mockS3Disk->shouldReceive('exists')->andReturn(false);

        Storage::set('s3', $mockS3Disk);

        $file = UploadedFile::fake()->image('s3_failure.png', 200, 200);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'shop_logo' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['shop_logo']]);
        // Secret keys must never appear in response
        $this->assertStringNotContainsString('mock_secret', $response->getContent());
    }

    /**
     * 38. Supabase S3 disk configuration auto-derives public object URL when AWS_URL is null.
     */
    public function test_supabase_s3_disk_auto_derives_public_object_url_when_aws_url_is_null(): void
    {
        $endpoint = 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3';
        $bucket = 'bakery-media';
        $derivedUrl = str_replace('.storage.supabase.co/storage/v1/s3', '.supabase.co/storage/v1/object/public/' . $bucket, $endpoint);

        config([
            'filesystems.disks.s3.key' => 'mock_key',
            'filesystems.disks.s3.secret' => 'mock_secret',
            'filesystems.disks.s3.region' => 'ap-northeast-1',
            'filesystems.disks.s3.bucket' => $bucket,
            'filesystems.disks.s3.endpoint' => $endpoint,
            'filesystems.disks.s3.use_path_style_endpoint' => true,
            'filesystems.disks.s3.url' => $derivedUrl,
        ]);

        $url = $this->storageDisk('s3')->url('logos/test_logo.png');
        $this->assertEquals(
            'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/logos/test_logo.png',
            $url
        );
    }

    /**
     * 39. Supabase S3 PutObject requests remove ACL parameters and omit x-amz-acl header.
     */
    public function test_supabase_s3_put_object_removes_acl_and_omits_x_amz_acl_header(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result());

        config([
            'filesystems.disks.test_supabase_acl' => [
                'driver' => 'supabase-s3',
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'region' => 'ap-northeast-1',
                'bucket' => 'bakery-media',
                'endpoint' => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
                'use_path_style_endpoint' => true,
                'handler' => $mock,
                'throw' => true,
                'report' => true,
            ],
        ]);

        $disk = $this->storageDisk('test_supabase_acl');
        $disk->put('logos/unit_test.txt', 'test contents');

        /** @var RequestInterface $interceptedRequest */
        $interceptedRequest = $mock->getLastRequest();
        $this->assertNotNull($interceptedRequest);
        // Outgoing HTTP request must NOT have x-amz-acl header
        $this->assertFalse($interceptedRequest->hasHeader('x-amz-acl'));
    }

    /**
     * 40. AWS Signature V4 does not sign x-amz-acl for Supabase S3 requests.
     */
    public function test_supabase_s3_sigv4_does_not_sign_x_amz_acl(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result());

        config([
            'filesystems.disks.test_supabase_sigv4' => [
                'driver' => 'supabase-s3',
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'region' => 'ap-northeast-1',
                'bucket' => 'bakery-media',
                'endpoint' => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
                'use_path_style_endpoint' => true,
                'handler' => $mock,
                'throw' => true,
                'report' => true,
            ],
        ]);

        $disk = $this->storageDisk('test_supabase_sigv4');
        $disk->put('logos/sigv4_test.txt', 'sigv4 test payload');

        /** @var RequestInterface $interceptedRequest */
        $interceptedRequest = $mock->getLastRequest();
        $this->assertNotNull($interceptedRequest);
        $authHeader = $interceptedRequest->getHeaderLine('Authorization');
        $this->assertNotEmpty($authHeader);
        // Signature V4 SignedHeaders must NOT contain x-amz-acl
        $this->assertStringNotContainsString('x-amz-acl', $authHeader);
        $this->assertStringContainsString('AWS4-HMAC-SHA256', $authHeader);
    }

    /**
     * 41. Supabase S3 custom driver supports upload and delete operations.
     */
    public function test_supabase_s3_driver_upload_and_delete_operations_work(): void
    {
        $mock = new MockHandler();
        // 1. PutObject response
        $mock->append(new Result());
        // 2. HeadObject response for exists() check
        $mock->append(new Result(['ContentLength' => 12]));
        // 3. DeleteObject response
        $mock->append(new Result());

        config([
            'filesystems.disks.test_supabase_ops' => [
                'driver' => 'supabase-s3',
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'region' => 'ap-northeast-1',
                'bucket' => 'bakery-media',
                'endpoint' => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
                'use_path_style_endpoint' => true,
                'handler' => $mock,
                'throw' => true,
                'report' => true,
            ],
        ]);

        $disk = $this->storageDisk('test_supabase_ops');

        // Write file
        $putResult = $disk->put('logos/ops_test.png', 'fake image bytes');
        $this->assertTrue($putResult);

        // Check existence
        $existsResult = $disk->exists('logos/ops_test.png');
        $this->assertTrue($existsResult);

        // Delete file
        $deleteResult = $disk->delete('logos/ops_test.png');
        $this->assertTrue($deleteResult);
    }

    /**
     * 42. Supabase-s3 dedicated disk generates correct public object URL.
     */
    public function test_supabase_s3_disk_generates_correct_public_object_url(): void
    {
        $endpoint = 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3';
        $bucket = 'bakery-media';
        $derivedUrl = str_replace('.storage.supabase.co/storage/v1/s3', '.supabase.co/storage/v1/object/public/' . $bucket, $endpoint);

        config([
            'filesystems.disks.supabase-s3.key' => 'mock_key',
            'filesystems.disks.supabase-s3.secret' => 'mock_secret',
            'filesystems.disks.supabase-s3.region' => 'ap-northeast-1',
            'filesystems.disks.supabase-s3.bucket' => $bucket,
            'filesystems.disks.supabase-s3.endpoint' => $endpoint,
            'filesystems.disks.supabase-s3.use_path_style_endpoint' => true,
            'filesystems.disks.supabase-s3.url' => $derivedUrl,
        ]);

        $url = $this->storageDisk('supabase-s3')->url('logos/supabase_logo.png');
        $this->assertEquals(
            'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/logos/supabase_logo.png',
            $url
        );
    }

    /**
     * 43. Failed upload to Supabase S3 preserves database values and previous logo.
     */
    public function test_failed_upload_to_supabase_s3_preserves_database_and_previous_logo(): void
    {
        SettingsService::set('shop_logo', 'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/logos/initial_logo.png');
        config(['filesystems.settings_disk' => 'supabase-s3']);

        /** @var \Illuminate\Contracts\Filesystem\Filesystem&MockInterface $mockDisk */
        $mockDisk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('putFileAs')
            ->andThrow(new \League\Flysystem\UnableToWriteFile('Connection refused to Supabase S3 endpoint'));
        $mockDisk->shouldReceive('exists')->andReturn(false);

        Storage::set('supabase-s3', $mockDisk);

        $file = UploadedFile::fake()->image('failed_logo.png', 200, 200);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'shop_logo' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['shop_logo']]);
        // Sensitive connection info should not be leaked
        $this->assertStringNotContainsString('Connection refused', $response->getContent());

        // Previous logo in database must remain unchanged
        $this->assertEquals(
            'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/logos/initial_logo.png',
            SettingsService::get('shop_logo')
        );
    }

    /**
     * 44. Settings update without logo upload continues to work with Supabase disk.
     */
    public function test_settings_update_without_logo_upload_continues_to_work_with_supabase_disk(): void
    {
        config(['filesystems.settings_disk' => 'supabase-s3']);

        $response = $this->actingAs($this->admin)->json('POST', route('admin.settings.update'), [
            'shop_name'    => 'Moon Cake Fresh Bakery',
            'shop_phone'   => '+855 12 345 678',
            'shop_email'   => 'bakery@example.com',
            'tax_rate'     => 10,
            'currency'     => 'KHR',
            'opening_time' => '07:00',
            'closing_time' => '21:00',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals('Moon Cake Fresh Bakery', SettingsService::get('shop_name'));
        $this->assertEquals('+855 12 345 678', SettingsService::get('shop_phone'));
        $this->assertEquals('bakery@example.com', SettingsService::get('shop_email'));
    }

    /**
     * 45. Production Supabase S3 endpoint and path-style compatibility audit.
     */
    public function test_production_supabase_s3_endpoint_and_path_style_compatibility(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result());

        $endpoint = 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3';
        $bucket = 'bakery-media';
        $publicBaseUrl = 'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/' . $bucket;

        config([
            'filesystems.disks.prod_audit_supabase' => [
                'driver' => 'supabase-s3',
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'region' => 'ap-northeast-1',
                'bucket' => $bucket,
                'endpoint' => $endpoint,
                'url' => $publicBaseUrl,
                'use_path_style_endpoint' => true,
                'handler' => $mock,
                'throw' => true,
                'report' => true,
            ],
        ]);

        $disk = $this->storageDisk('prod_audit_supabase');
        $fileName = 'bakery_logo_1730000000_abcd1234.png';
        $disk->put('logos/' . $fileName, 'audit image binary content');

        /** @var RequestInterface $interceptedRequest */
        $interceptedRequest = $mock->getLastRequest();
        $this->assertNotNull($interceptedRequest);

        // 1. Confirm HTTP Host is exact Supabase storage host
        $this->assertEquals('rtrnlnezucgjkgqluofv.storage.supabase.co', $interceptedRequest->getHeaderLine('Host'));
        $this->assertEquals('rtrnlnezucgjkgqluofv.storage.supabase.co', $interceptedRequest->getUri()->getHost());

        // 2. Confirm request path includes /storage/v1/s3/bakery-media/logos/
        $requestPath = $interceptedRequest->getUri()->getPath();
        $this->assertEquals('/storage/v1/s3/bakery-media/logos/' . $fileName, $requestPath);
        $this->assertStringStartsWith('/storage/v1/s3/bakery-media/logos/', $requestPath);

        // 3. Confirm bucket is NOT inserted as a subdomain
        $this->assertStringNotContainsString('bakery-media.storage.supabase.co', $interceptedRequest->getHeaderLine('Host'));
        $this->assertStringNotContainsString('bakery-media.storage.supabase.co', (string) $interceptedRequest->getUri());

        // 4. Confirm x-amz-acl is completely absent from request headers
        $this->assertFalse($interceptedRequest->hasHeader('x-amz-acl'));

        // 5. Confirm AWS Signature V4 signs the correct request without x-amz-acl
        $authHeader = $interceptedRequest->getHeaderLine('Authorization');
        $this->assertStringContainsString('AWS4-HMAC-SHA256', $authHeader);
        $this->assertStringContainsString('Credential=AKIAIOSFODNN7EXAMPLE/20261107/ap-northeast-1/s3/aws4_request', $authHeader);
        $this->assertStringNotContainsString('x-amz-acl', $authHeader);

        // 6. Verify public URL uses https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/
        $generatedUrl = $disk->url('logos/' . $fileName);
        $this->assertEquals(
            'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/logos/' . $fileName,
            $generatedUrl
        );
        $this->assertStringStartsWith('https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media/', $generatedUrl);
    }
}
