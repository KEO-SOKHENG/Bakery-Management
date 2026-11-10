<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductShelfLifeAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_expiry_test'],
            [
                'name' => 'Admin Expiry',
                'email' => 'admin_expiry@test.com',
                'password' => Hash::make('pass123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $this->category = Category::firstOrCreate(
            ['name' => 'Pastries'],
            ['description' => 'Delicious pastries', 'status' => 'active']
        );
    }

    public function test_product_has_shelf_life_and_expiry_date_attributes()
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Croissant',
            'sku' => 'CRO-001',
            'price' => 3.50,
            'cost' => 1.20,
            'stock' => 15,
            'minimum_stock' => 5,
            'shelf_life' => 7,
            'expiry_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'active',
        ]);

        $this->assertEquals(7, $product->shelf_life);
        $this->assertEquals(Carbon::now()->addDays(7)->toDateString(), $product->expiry_date->toDateString());
    }

    public function test_product_expiry_helper_methods()
    {
        // Expired product
        $expiredProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Old Eclair',
            'sku' => 'ECL-OLD',
            'price' => 2.50,
            'cost' => 1.00,
            'stock' => 5,
            'shelf_life' => 3,
            'expiry_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $this->assertTrue($expiredProduct->isExpired());
        $this->assertFalse($expiredProduct->isExpiringSoon(3));

        // Expiring soon product (2 days left)
        $expiringSoonProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Fresh Eclair',
            'sku' => 'ECL-SOON',
            'price' => 2.50,
            'cost' => 1.00,
            'stock' => 10,
            'shelf_life' => 3,
            'expiry_date' => Carbon::now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);

        $this->assertFalse($expiringSoonProduct->isExpired());
        $this->assertTrue($expiringSoonProduct->isExpiringSoon(3));

        // Fresh product (10 days left)
        $freshProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Durable Cookie',
            'sku' => 'COO-FRESH',
            'price' => 1.50,
            'cost' => 0.50,
            'stock' => 25,
            'shelf_life' => 14,
            'expiry_date' => Carbon::now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $this->assertFalse($freshProduct->isExpired());
        $this->assertFalse($freshProduct->isExpiringSoon(3));
    }

    public function test_admin_can_create_product_with_shelf_life_and_expiry()
    {
        $expiry = Carbon::now()->addDays(5)->toDateString();

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Croissant Supreme',
            'category_id' => $this->category->id,
            'price' => 3.50,
            'cost' => 1.20,
            'stock' => 20,
            'minimum_stock' => 5,
            'shelf_life' => 5,
            'expiry_date' => $expiry,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('name', 'Croissant Supreme')->first();
        $this->assertNotNull($product);
        $this->assertEquals(5, $product->shelf_life);
        $this->assertEquals($expiry, $product->expiry_date->toDateString());
    }

    public function test_completing_production_batch_auto_calculates_expiry_date()
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Batch Bread',
            'sku' => 'BAT-001',
            'price' => 2.00,
            'cost' => 0.80,
            'stock' => 10,
            'shelf_life' => 4,
            'expiry_date' => null,
            'status' => 'active',
        ]);

        $production = Production::create([
            'batch_number' => 'BATCH-EXP-001',
            'product_id' => $product->id,
            'quantity' => 10,
            'production_date' => now(),
            'status' => 'in_progress',
            'user_id' => $this->admin->id,
            'baker_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.production.updateStatus', $production), [
            'status' => 'completed',
        ]);

        $response->assertRedirect();

        $product->refresh();
        $expectedExpiry = Carbon::now()->addDays(4)->toDateString();
        $this->assertEquals($expectedExpiry, $product->expiry_date?->toDateString());
    }

    public function test_product_can_be_updated_with_validated_fields()
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Original Brioche',
            'sku' => 'BRI-001',
            'price' => 4.00,
            'cost' => 1.50,
            'stock' => 12,
            'minimum_stock' => 4,
            'shelf_life' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'category_id' => $this->category->id,
            'name' => 'Updated Golden Brioche',
            'sku' => 'BRI-001',
            'price' => 5.50,
            'cost' => 2.00,
            'stock' => 20,
            'minimum_stock' => 6,
            'shelf_life' => 5,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'description' => 'Updated rich brioche loaf',
        ]);

        $response->assertRedirect(route('admin.products'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Golden Brioche',
            'price' => 5.50,
            'stock' => 20,
            'shelf_life' => 5,
        ]);
    }

    public function test_product_can_be_created_and_updated_with_uploaded_image()
    {
        Storage::fake('public'); // media disk in tests; images are stored under products/
        $fakeImage = UploadedFile::fake()->image('bakery_tart.jpg', 300, 300);

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'category_id' => $this->category->id,
            'name' => 'Fruit Tart with Image',
            'sku' => 'TART-IMG-01',
            'price' => 6.00,
            'stock' => 8,
            'image' => $fakeImage,
        ]);

        $response->assertRedirect(route('admin.products'));
        $product = Product::where('sku', 'TART-IMG-01')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image_path);
        $this->assertStringStartsWith('products/', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_product_view_renders_edit_and_image_upload_ui()
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'View Test Cake',
            'sku' => 'VTC-001',
            'price' => 12.00,
            'stock' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products'));
        $response->assertStatus(200);
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('id="create_product_image"', false);
        $response->assertSee('id="edit_product_modal"', false);
        $response->assertSee('btn-edit-product', false);
    }

    public function test_updating_product_without_new_image_preserves_existing_image()
    {
        Storage::fake('public');
        $fakeImage = UploadedFile::fake()->image('original_bun.jpg', 200, 200);

        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'category_id' => $this->category->id,
            'name' => 'Original Bun',
            'sku' => 'BUN-PRESERVE-01',
            'price' => 2.50,
            'stock' => 15,
            'image' => $fakeImage,
        ]);

        $product = Product::where('sku', 'BUN-PRESERVE-01')->firstOrFail();
        $originalImagePath = $product->image_path;
        $this->assertNotNull($originalImagePath);
        Storage::disk('public')->assertExists($originalImagePath);

        // Update product WITHOUT providing an image
        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'category_id' => $this->category->id,
            'name' => 'Renamed Bun with Same Image',
            'sku' => 'BUN-PRESERVE-01',
            'price' => 3.00,
            'stock' => 20,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.products'));
        $product->refresh();

        $this->assertEquals('Renamed Bun with Same Image', $product->name);
        $this->assertEquals(3.00, (float)$product->price);
        $this->assertEquals($originalImagePath, $product->image_path, 'Existing image path should be preserved when no new image is uploaded');
        Storage::disk('public')->assertExists($originalImagePath);
    }
}
