<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductRecipeIngredientsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected Ingredient $flour;
    protected Ingredient $sugar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_test_recipe'],
            [
                'name' => 'Admin Recipe',
                'email' => 'admin_recipe@test.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $this->category = Category::create([
            'name' => 'Cakes & Pastries',
            'status' => 'active',
        ]);

        $this->flour = Ingredient::create([
            'name' => 'All-Purpose Flour',
            'unit' => 'kg',
            'quantity' => 100.0,
            'cost' => 1.50,
            'minimum_quantity' => 10.0,
            'status' => 'active',
        ]);

        $this->sugar = Ingredient::create([
            'name' => 'Cane Sugar',
            'unit' => 'kg',
            'quantity' => 50.0,
            'cost' => 2.00,
            'minimum_quantity' => 5.0,
            'status' => 'active',
        ]);
    }

    public function test_ingredients_list_endpoint_returns_json_of_active_ingredients(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.products.ingredients-list'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'ingredients' => [
                '*' => ['id', 'name', 'unit', 'quantity', 'cost', 'status'],
            ],
        ]);
        $response->assertJsonFragment(['name' => 'All-Purpose Flour']);
        $response->assertJsonFragment(['name' => 'Cane Sugar']);
    }

    public function test_can_create_product_with_recipe_ingredients(): void
    {
        $payload = [
            'category_id' => $this->category->id,
            'name' => 'Strawberry Shortcake',
            'price' => 15.00,
            'stock' => 20,
            'status' => 'active',
            'ingredients' => [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 0.5,
                    'unit' => 'kg',
                ],
                [
                    'ingredient_id' => $this->sugar->id,
                    'quantity' => 0.25,
                    'unit' => 'kg',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), $payload);

        $response->assertRedirect(route('admin.products'));
        $response->assertSessionHas('success');

        $product = Product::where('name', 'Strawberry Shortcake')->first();
        $this->assertNotNull($product);

        // Expected cost: (0.5 * 1.50) + (0.25 * 2.00) = 0.75 + 0.50 = 1.25
        $this->assertEquals(1.25, (float) $product->cost);

        $this->assertNotNull($product->recipe);
        $this->assertCount(2, $product->recipe->recipeIngredients);

        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $product->recipe->id,
            'ingredient_id' => $this->flour->id,
            'quantity' => 0.5,
        ]);
    }

    public function test_can_update_product_recipe_ingredients(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Vanilla Muffin',
            'price' => 3.00,
            'cost' => 0.50,
            'stock' => 15,
            'status' => 'active',
        ]);

        $updatePayload = [
            'category_id' => $this->category->id,
            'name' => 'Vanilla Muffin',
            'price' => 3.50,
            'cost' => 0,
            'stock' => 15,
            'status' => 'active',
            'ingredients' => [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 1.0,
                    'unit' => 'kg',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), $updatePayload);

        $response->assertRedirect(route('admin.products'));
        $product->refresh();

        $this->assertNotNull($product->recipe);
        $this->assertCount(1, $product->recipe->recipeIngredients);
        // Cost should be updated to 1.0 * 1.50 = 1.50
        $this->assertEquals(1.50, (float) $product->cost);
    }

    public function test_products_index_renders_successfully_with_ingredients(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products'));

        $response->assertStatus(200);
        $response->assertViewHas('ingredients');
        $response->assertSee('Recipe Ingredients');
        $response->assertSee('Live Sync');
    }
}
