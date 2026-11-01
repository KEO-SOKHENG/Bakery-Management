<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategorySafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_cat_test'],
            [
                'name' => 'Admin Cat',
                'email' => 'admin_cat@test.com',
                'password' => Hash::make('pass123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
    }

    public function test_empty_category_can_be_deleted()
    {
        $category = Category::create([
            'name' => 'Empty Breads',
            'description' => 'No products in this category',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_products_cannot_be_deleted()
    {
        $category = Category::create([
            'name' => 'Cakes & Pies',
            'description' => 'Category with products',
            'status' => 'active',
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Strawberry Tart',
            'sku' => 'TART-001',
            'price' => 4.50,
            'cost' => 1.50,
            'stock' => 10,
            'minimum_stock' => 2,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_category_can_be_updated()
    {
        $category = Category::create([
            'name' => 'Original Breads',
            'description' => 'Original Description',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.categories.update', $category), [
            'name' => 'Updated Breads',
            'description' => 'Updated Description',
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('admin.categories'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Breads',
            'description' => 'Updated Description',
            'status' => 'inactive',
        ]);
    }

    public function test_categories_can_be_searched()
    {
        Category::create(['name' => 'Artisan Bagels', 'status' => 'active']);
        Category::create(['name' => 'Sourdough Delights', 'status' => 'active']);

        $response = $this->actingAs($this->admin)->get(route('admin.categories', ['search' => 'Bagels']));
        $response->assertStatus(200);
        $response->assertSee('Artisan Bagels');
        $response->assertDontSee('Sourdough Delights');
    }

    public function test_category_view_renders_edit_and_search_ui()
    {
        $category = Category::create(['name' => 'Gluten Free', 'status' => 'active']);

        $response = $this->actingAs($this->admin)->get(route('admin.categories'));
        $response->assertStatus(200);
        $response->assertSee('id="category_search_input"', false);
        $response->assertSee('id="edit_category_modal"', false);
        $response->assertSee('btn-edit-category', false);
    }
}
