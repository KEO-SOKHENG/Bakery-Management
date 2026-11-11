<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Rules\UniqueProductName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductUniqueNameTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected Product $existing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin Unique',
            'username' => 'admin_unique_test',
            'email'    => 'admin_unique@test.com',
            'password' => Hash::make('pass123'),
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        $this->category = Category::create(['name' => 'Breads', 'status' => 'active']);

        $this->existing = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Chocolate Cake',
            'sku'         => 'CAKE-01',
            'price'       => 10,
            'cost'        => 4,
            'stock'       => 5,
            'status'      => 'active',
        ]);
    }

    // ── Helpers ──

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name'        => 'Vanilla Cake',
            'price'       => 9,
            'stock'       => 3,
        ], $overrides);
    }

    private function create(string $name)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.products'))
            ->post(route('admin.products.store'), $this->payload(['name' => $name]));
    }

    private function updateExisting(Product $product, string $name)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.products'))
            ->put(route('admin.products.update', $product), $this->payload([
                'name' => $name,
                'sku'  => $product->sku,
            ]));
    }

    // ── Create ──

    public function test_create_rejects_exact_duplicate_name(): void
    {
        $this->create('Chocolate Cake')
            ->assertRedirect(route('admin.products'))
            ->assertSessionHasErrors(['name' => UniqueProductName::MESSAGE]);

        $this->assertSame(1, Product::count());
    }

    public function test_create_rejects_duplicate_with_different_letter_case(): void
    {
        foreach (['chocolate cake', 'CHOCOLATE CAKE', 'cHoCoLaTe CaKe'] as $name) {
            $this->create($name)->assertSessionHasErrors(['name' => UniqueProductName::MESSAGE]);
        }

        $this->assertSame(1, Product::count());
    }

    public function test_create_rejects_duplicate_with_surrounding_whitespace(): void
    {
        $this->create('   Chocolate Cake   ')
            ->assertSessionHasErrors(['name' => UniqueProductName::MESSAGE]);

        $this->create("\tchocolate cake ")
            ->assertSessionHasErrors(['name' => UniqueProductName::MESSAGE]);

        $this->assertSame(1, Product::count());
    }

    public function test_create_stores_trimmed_name_for_new_product(): void
    {
        $this->create('  Vanilla Cake  ')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.products'));

        $this->assertDatabaseHas('products', ['name' => 'Vanilla Cake']);
    }

    public function test_create_allows_names_that_only_share_a_prefix(): void
    {
        $this->create('Chocolate Cake Slice')->assertSessionHasNoErrors();

        $this->assertSame(2, Product::count());
    }

    // ── Update ──

    public function test_update_keeping_own_name_is_allowed(): void
    {
        $this->updateExisting($this->existing, 'Chocolate Cake')->assertSessionHasNoErrors();
    }

    public function test_update_changing_case_of_own_name_is_allowed(): void
    {
        $this->updateExisting($this->existing, '  chocolate CAKE ')->assertSessionHasNoErrors();

        $this->assertSame('chocolate CAKE', $this->existing->fresh()->name);
    }

    public function test_update_to_another_products_name_is_rejected(): void
    {
        $other = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Lemon Tart',
            'sku'         => 'TART-01',
            'price'       => 4,
            'stock'       => 2,
            'status'      => 'active',
        ]);

        foreach (['Chocolate Cake', 'CHOCOLATE cake', '  chocolate cake  '] as $name) {
            $this->updateExisting($other, $name)
                ->assertSessionHasErrors(['name' => UniqueProductName::MESSAGE]);
        }

        $this->assertSame('Lemon Tart', $other->fresh()->name);
    }

    // ── UI ──

    public function test_products_page_shows_the_duplicate_message(): void
    {
        $this->create('chocolate cake');

        $this->actingAs($this->admin)
            ->get(route('admin.products'))
            ->assertSee(UniqueProductName::MESSAGE);
    }
}
