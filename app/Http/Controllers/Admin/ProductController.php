<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'recipe.recipeIngredients.ingredient']);

        if ($request->has('search') && !empty($request->search)) {
            $search = trim($request->search);
            $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('sku', $like, "%{$search}%")
                  ->orWhere('description', $like, "%{$search}%");
            });
        }

        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $products = $query->orderBy('id', 'desc')->get();
        $categories = Category::where('status', 'active')->get();
        $ingredients = Ingredient::where('status', 'active')->orderBy('name', 'asc')->get();

        return view('admin.products', compact('products', 'categories', 'ingredients'));
    }

    public function ingredientsList()
    {
        $ingredients = Ingredient::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'unit', 'quantity', 'cost', 'status']);

        return response()->json([
            'success' => true,
            'count' => $ingredients->count(),
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'shelf_life' => 'nullable|integer|min:1',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive',
            'image_emoji' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'ingredients' => 'nullable',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/products'), $filename);
            $imagePath = 'uploads/products/' . $filename;
        }

        if (empty($validated['sku'])) {
            $maxId = Product::max('id') ?? 0;
            $validated['sku'] = 'PRD-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        }

        $shelfLife = !empty($validated['shelf_life']) ? (int) $validated['shelf_life'] : null;
        $expiryDate = !empty($validated['expiry_date']) 
            ? $validated['expiry_date'] 
            : ($shelfLife ? now()->addDays($shelfLife)->toDateString() : null);

        $product = Product::create([
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0.00,
            'stock' => $validated['stock'],
            'minimum_stock' => $validated['minimum_stock'] ?? 5,
            'shelf_life' => $shelfLife,
            'expiry_date' => $expiryDate,
            'status' => $validated['status'] ?? 'active',
            'image_emoji' => $validated['image_emoji'] ?? '🥖',
            'image_path' => $imagePath,
        ]);

        // Process Recipe & Ingredients if provided
        $rawIngredients = $request->input('ingredients');
        if (is_string($rawIngredients)) {
            $rawIngredients = json_decode($rawIngredients, true) ?: [];
        }
        $validIngredients = collect($rawIngredients)
            ->filter(function ($item) {
                return !empty($item['ingredient_id']) && isset($item['quantity']) && (float) $item['quantity'] > 0;
            })
            ->values();

        if ($validIngredients->isNotEmpty()) {
            $recipe = Recipe::create([
                'product_id' => $product->id,
                'name' => $product->name . ' Recipe',
                'description' => 'Recipe formula for ' . $product->name,
                'instructions' => 'Standard recipe preparation',
                'yield_quantity' => 1,
                'production_time' => 30,
                'status' => 'active',
            ]);

            $calculatedCost = 0.0;
            foreach ($validIngredients as $item) {
                $ing = Ingredient::find($item['ingredient_id']);
                if ($ing) {
                    $unit = !empty($item['unit']) ? $item['unit'] : $ing->unit;
                    $qty = (float) $item['quantity'];
                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ing->id,
                        'quantity' => $qty,
                        'unit' => $unit,
                    ]);
                    $calculatedCost += $qty * (float) $ing->cost;
                }
            }

            if (empty($validated['cost']) || (float) $validated['cost'] == 0) {
                $product->update(['cost' => round($calculatedCost, 2)]);
            }
        }

        return redirect()->route('admin.products')->with('success', 'Product created successfully!');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'shelf_life' => 'nullable|integer|min:1',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive',
            'image_emoji' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'ingredients' => 'nullable',
        ]);

        $validated['status'] = $validated['status'] ?? $product->status ?? 'active';

        if ($request->hasFile('image')) {
            if ($product->image_path && file_exists(public_path($product->image_path))) {
                @unlink(public_path($product->image_path));
            }
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/products'), $filename);
            $validated['image_path'] = 'uploads/products/' . $filename;
        }

        $product->update($validated);

        // Process Recipe & Ingredients if provided
        if ($request->has('ingredients')) {
            $rawIngredients = $request->input('ingredients');
            if (is_string($rawIngredients)) {
                $rawIngredients = json_decode($rawIngredients, true) ?: [];
            }
            $validIngredients = collect($rawIngredients)
                ->filter(function ($item) {
                    return !empty($item['ingredient_id']) && isset($item['quantity']) && (float) $item['quantity'] > 0;
                })
                ->values();

            if ($validIngredients->isNotEmpty()) {
                $recipe = $product->recipe ?: Recipe::create([
                    'product_id' => $product->id,
                    'name' => $product->name . ' Recipe',
                    'description' => 'Recipe formula for ' . $product->name,
                    'instructions' => 'Standard recipe preparation',
                    'yield_quantity' => 1,
                    'production_time' => 30,
                    'status' => 'active',
                ]);

                // Sync recipe ingredients
                $recipe->recipeIngredients()->delete();

                $calculatedCost = 0.0;
                foreach ($validIngredients as $item) {
                    $ing = Ingredient::find($item['ingredient_id']);
                    if ($ing) {
                        $unit = !empty($item['unit']) ? $item['unit'] : $ing->unit;
                        $qty = (float) $item['quantity'];
                        RecipeIngredient::create([
                            'recipe_id' => $recipe->id,
                            'ingredient_id' => $ing->id,
                            'quantity' => $qty,
                            'unit' => $unit,
                        ]);
                        $calculatedCost += $qty * (float) $ing->cost;
                    }
                }

                if (empty($validated['cost']) || (float) $validated['cost'] == 0) {
                    $product->update(['cost' => round($calculatedCost, 2)]);
                }
            } elseif ($product->recipe) {
                // If ingredients array was submitted empty, clear recipe ingredients
                $product->recipe->recipeIngredients()->delete();
            }
        }

        return redirect()->route('admin.products')->with('success', 'Product updated successfully!');
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()) {
            return redirect()->route('admin.products')
                ->with('error', "Cannot delete product '{$product->name}': Historical sales and orders reference this item. Consider setting its status to inactive instead.");
        }

        if ($product->productions()->exists()) {
            return redirect()->route('admin.products')
                ->with('error', "Cannot delete product '{$product->name}': Production records reference this item.");
        }

        if ($product->recipe()->exists()) {
            return redirect()->route('admin.products')
                ->with('error', "Cannot delete product '{$product->name}': A recipe is linked to this item. Please delete or reassign the recipe first.");
        }

        if ($product->image_path && file_exists(public_path($product->image_path))) {
            @unlink(public_path($product->image_path));
        }

        $product->delete();

        return redirect()->route('admin.products')->with('success', 'Product deleted successfully!');
    }
}
