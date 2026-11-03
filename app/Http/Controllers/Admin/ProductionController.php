<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Product;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isBaker = $user && $user->role === 'baker';

        // 1. Base Query with Eager Loading
        $query = Production::with([
            'recipe.ingredients',
            'product.category',
            'baker',
            'creator'
        ]);

        // 2. Search Filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('batch_number', $like, "%{$search}%")
                  ->orWhere('notes', $like, "%{$search}%")
                  ->orWhereHas('product', function ($p) use ($search, $like) {
                      $p->where('name', $like, "%{$search}%")
                        ->orWhere('sku', $like, "%{$search}%");
                  })
                  ->orWhereHas('baker', function ($b) use ($search, $like) {
                      $b->where('name', $like, "%{$search}%");
                  });
            });
        }

        // 3. Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtolower($request->status));
        }

        // 4. Product Filter
        if ($request->filled('product_id') && $request->product_id !== 'all') {
            $query->where('product_id', $request->product_id);
        }

        // 5. Baker Filter
        if ($request->filled('baker_id') && $request->baker_id !== 'all') {
            $query->where('baker_id', $request->baker_id);
        }

        // 6. Date Filter
        if ($request->filled('date')) {
            $date = Carbon::parse($request->date)->toDateString();
            $query->where(function ($q) use ($date) {
                $q->whereDate('scheduled_at', $date)
                  ->orWhereDate('production_date', $date);
            });
        }

        // 7. Tab Filter
        $tab = $request->input('tab', $isBaker ? 'mine' : 'all');
        if ($tab === 'mine' && $user) {
            $query->where('baker_id', $user->id);
        } elseif ($tab === 'active') {
            $query->whereIn('status', ['scheduled', 'in_progress']);
        } elseif ($tab === 'history') {
            $query->whereIn('status', ['completed', 'cancelled']);
        }

        $productions = $query->orderBy('created_at', 'desc')->get();

        // 8. Aggregate Summary Stats (Consolidated single aggregation query)
        $bakerId = $user?->id ?? 0;
        $prodSummary = Production::query()
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN quantity ELSE 0 END), 0) as completed_units,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN baker_id = ? THEN 1 END) as my_batches
            ", [$bakerId])
            ->first();

        $stats = [
            'total'           => (int) ($prodSummary->total ?? 0),
            'scheduled'       => (int) ($prodSummary->scheduled ?? 0),
            'in_progress'     => (int) ($prodSummary->in_progress ?? 0),
            'completed'       => (int) ($prodSummary->completed ?? 0),
            'completed_units' => (int) ($prodSummary->completed_units ?? 0),
            'cancelled'       => (int) ($prodSummary->cancelled ?? 0),
            'my_batches'      => (int) ($prodSummary->my_batches ?? 0),
        ];

        // 9. Active Products with linked Recipe & Ingredients (for live modal preview)
        $products = Product::with(['recipe.ingredients', 'category'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $productsJson = $products->mapWithKeys(function ($p) {
            $recipeData = null;
            if ($p->recipe) {
                $recipeData = [
                    'id'           => $p->recipe->id,
                    'name'         => $p->recipe->name,
                    'instructions' => $p->recipe->instructions,
                    'ingredients'  => $p->recipe->ingredients->map(function ($ing) {
                        return [
                            'id'            => $ing->id,
                            'name'          => $ing->name,
                            'unit'          => $ing->unit ?? $ing->pivot->unit,
                            'unit_quantity' => (float) $ing->pivot->quantity,
                            'current_stock' => (float) $ing->quantity,
                        ];
                    }),
                ];
            }
            return [$p->id => [
                'id'          => $p->id,
                'name'        => $p->name,
                'sku'         => $p->sku,
                'stock'       => $p->stock,
                'image_emoji' => $p->image_emoji ?? '🥖',
                'recipe'      => $recipeData,
            ]];
        });

        // 10. Bakers dropdown: users with role baker, manager, or admin
        $bakers = User::whereIn('role', ['baker', 'manager', 'admin'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.production', compact(
            'productions',
            'products',
            'productsJson',
            'bakers',
            'stats',
            'tab'
        ));
    }

    public function show(Request $request, Production $production)
    {
        $production->loadMissing([
            'recipe.ingredients.supplier',
            'product.category',
            'baker',
            'creator'
        ]);

        $requiredIngredients = $production->getRequiredIngredients();

        if ($request->wantsJson() || $request->ajax()) {
            $user = Auth::user();
            $isAssignedToUser = $user && ($user->role !== 'baker' || $user->id === $production->baker_id);

            return response()->json([
                'success' => true,
                'batch' => [
                    'id' => $production->id,
                    'batch_number' => $production->batch_number ?? ('PROD-' . str_pad($production->id, 4, '0', STR_PAD_LEFT)),
                    'status' => strtolower($production->status),
                    'status_label' => ucfirst(str_replace('_', ' ', $production->status)),
                    'quantity' => $production->quantity,
                    'scheduled_at' => $production->scheduled_at ? $production->scheduled_at->format('M d, Y h:i A') : ($production->production_date ? $production->production_date->format('M d, Y') : '-'),
                    'started_at' => $production->started_at ? $production->started_at->format('M d, Y h:i A') : null,
                    'completed_at' => $production->completed_at ? $production->completed_at->format('M d, Y h:i A') : null,
                    'duration' => $production->duration,
                    'notes' => $production->notes,
                    'product' => [
                        'id' => $production->product_id,
                        'name' => $production->product->name ?? 'Bakery Product',
                        'image_emoji' => $production->product->image_emoji ?? '🥖',
                        'category' => $production->product->category->name ?? 'Bakery',
                        'stock' => $production->product->stock ?? 0,
                        'sku' => $production->product->sku ?? 'N/A',
                    ],
                    'recipe' => $production->recipe ? [
                        'id' => $production->recipe->id,
                        'name' => $production->recipe->name,
                        'instructions' => $production->recipe->instructions ?? $production->recipe->description ?? 'No detailed instructions provided.',
                    ] : null,
                    'baker' => [
                        'id' => $production->baker_id,
                        'name' => $production->baker->name ?? 'Unassigned Baker',
                        'initials' => $production->baker ? strtoupper(substr($production->baker->name, 0, 2)) : 'UB',
                        'role' => $production->baker ? ucfirst($production->baker->role) : 'N/A',
                        'email' => $production->baker->email ?? '',
                    ],
                    'creator' => [
                        'name' => $production->creator->name ?? 'System',
                    ],
                    'ingredients' => $requiredIngredients->values()->all(),
                    'can_start' => $production->isScheduled() && $isAssignedToUser,
                    'can_complete' => $production->isInProgress() && $isAssignedToUser,
                    'can_cancel' => in_array(strtolower($production->status), ['scheduled', 'in_progress']) && $isAssignedToUser,
                    'update_url' => route('admin.production.updateStatus', $production->id),
                ]
            ]);
        }

        return redirect()->route('admin.production', ['open_batch' => $production->id]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|integer|min:1',
            'baker_id'     => 'nullable|exists:users,id',
            'scheduled_at' => 'nullable|date',
            'notes'        => 'nullable|string|max:1000',
            'status'       => 'nullable|string|in:scheduled,in_progress,completed',
            'batch_number' => 'nullable|string|max:50|unique:productions,batch_number',
        ]);

        $product = Product::with('recipe.ingredients')->findOrFail($validated['product_id']);
        $recipe = $product->recipe;
        $qty = (int) $validated['quantity'];
        $status = strtolower($validated['status'] ?? 'scheduled');
        $bakerId = $validated['baker_id'] ?? Auth::id();
        $scheduledAt = $validated['scheduled_at'] ? Carbon::parse($validated['scheduled_at']) : now();

        // Auto-generate batch number if empty
        $batchNumber = $validated['batch_number'] ?? $this->generateBatchNumber();

        try {
            $prod = DB::transaction(function () use ($product, $recipe, $qty, $status, $bakerId, $scheduledAt, $validated, $batchNumber) {
                $startedAt = null;
                $completedAt = null;

                if ($status === 'in_progress') {
                    $startedAt = now();
                    if ($recipe) {
                        $this->validateAndDeductIngredients($recipe, $qty);
                    }
                } elseif ($status === 'completed') {
                    $startedAt = now();
                    $completedAt = now();
                    if ($recipe) {
                        $this->validateAndDeductIngredients($recipe, $qty);
                    }
                    // Add stock to product
                    $product->lockForUpdate();
                    $product->increment('stock', $qty);
                }

                return Production::create([
                    'batch_number'    => $batchNumber,
                    'recipe_id'       => $recipe ? $recipe->id : null,
                    'product_id'      => $product->id,
                    'quantity'        => $qty,
                    'production_date' => $scheduledAt,
                    'scheduled_at'    => $scheduledAt,
                    'started_at'      => $startedAt,
                    'completed_at'    => $completedAt,
                    'status'          => $status,
                    'notes'           => $validated['notes'] ?? null,
                    'user_id'         => Auth::id(),
                    'baker_id'        => $bakerId,
                ]);
            });

            // Dispatch notification for scheduled or completed production batch
            try {
                if ($prod->status === 'scheduled') {
                    app(\App\Services\NotificationService::class)->notifyProductionScheduled($prod);
                } elseif ($prod->status === 'completed') {
                    app(\App\Services\NotificationService::class)->notifyProductionCompleted($prod);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch production notification: " . $e->getMessage());
            }

            return redirect()->route('admin.production')
                ->with('success', "Production batch #{$batchNumber} created successfully!");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request, Production $production)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:scheduled,in_progress,completed,cancelled',
        ]);

        $newStatus = strtolower($validated['status']);
        $oldStatus = strtolower($production->status);

        // 1. Role Authorization: Baker can only update batches assigned to them or if admin/manager
        $user = Auth::user();
        if ($user->role === 'baker' && $production->baker_id && $production->baker_id !== $user->id) {
            return back()->with('error', 'Unauthorized: You can only update production batches assigned to you.');
        }

        // 2. Validate workflow transitions
        if (!$production->canTransitionTo($newStatus)) {
            return back()->with('error', "Invalid transition: Cannot change batch status from " . ucfirst($oldStatus) . " to " . ucfirst($newStatus) . ".");
        }

        try {
            DB::transaction(function () use ($production, $oldStatus, $newStatus) {
                // Pessimistic lock on production record
                $prod = Production::lockForUpdate()->findOrFail($production->id);
                $recipe = $prod->recipe_id ? Recipe::with('ingredients')->find($prod->recipe_id) : null;
                $product = Product::lockForUpdate()->findOrFail($prod->product_id);

                // Workflow Action Matrix:
                if ($newStatus === 'in_progress') {
                    // Scheduled -> In Progress: validate & deduct ingredients
                    if ($recipe) {
                        $this->validateAndDeductIngredients($recipe, $prod->quantity);
                    }
                    $prod->started_at = now();
                } elseif ($newStatus === 'completed') {
                    // If moving directly from Scheduled to Completed
                    if ($oldStatus === 'scheduled' && $recipe) {
                        $this->validateAndDeductIngredients($recipe, $prod->quantity);
                        $prod->started_at = $prod->started_at ?? now();
                    }
                    // Increase product stock
                    $product->increment('stock', $prod->quantity);
                    if ($product->shelf_life && $product->shelf_life > 0) {
                        $product->update([
                            'expiry_date' => now()->addDays($product->shelf_life)->toDateString(),
                        ]);
                    }
                    $prod->completed_at = now();
                } elseif ($newStatus === 'cancelled') {
                    // If cancelled from In Progress, restore previously deducted ingredients
                    if ($oldStatus === 'in_progress' && $recipe) {
                        $this->restoreIngredients($recipe, $prod->quantity);
                    }
                }

                $prod->status = $newStatus;
                $prod->save();
            });

            // Dispatch notification if batch completed
            try {
                $freshProd = Production::find($production->id);
                if ($freshProd && $freshProd->status === 'completed') {
                    app(\App\Services\NotificationService::class)->notifyProductionCompleted($freshProd);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch production completion notification: " . $e->getMessage());
            }

            return redirect()->route('admin.production')
                ->with('success', "Batch #{$production->batch_number} updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . " successfully!");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Production $production)
    {
        // Completed productions cannot be deleted to preserve inventory audit history
        if ($production->isCompleted()) {
            return back()->with('error', 'Completed production batches cannot be deleted as inventory stock has already been updated.');
        }

        try {
            DB::transaction(function () use ($production) {
                // If in_progress, restore deducted ingredients before deletion
                if ($production->isInProgress() && $production->recipe_id) {
                    $recipe = Recipe::with('ingredients')->find($production->recipe_id);
                    if ($recipe) {
                        $this->restoreIngredients($recipe, $production->quantity);
                    }
                }

                $production->delete();
            });

            return redirect()->route('admin.production')
                ->with('success', "Production batch #{$production->batch_number} deleted successfully.");
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting production batch: ' . $e->getMessage());
        }
    }

    /**
     * Check ingredient stock with row locking and decrement if sufficient.
     * Throws an exception if any ingredient is deficient.
     */
    private function validateAndDeductIngredients(Recipe $recipe, int $quantity): void
    {
        $recipe->loadMissing('ingredients');

        foreach ($recipe->ingredients as $ing) {
            $requiredQty = round((float) $ing->pivot->quantity * $quantity, 2);

            // Row lock on ingredient
            $ingredient = Ingredient::lockForUpdate()->findOrFail($ing->id);

            if ((float) $ingredient->quantity < $requiredQty) {
                $available = number_format($ingredient->quantity, 2);
                $required = number_format($requiredQty, 2);
                $unit = $ingredient->unit ?? 'units';
                throw new \Exception("Insufficient stock for '{$ingredient->name}'. Required: {$required} {$unit}, Available: {$available} {$unit}. Transaction rolled back.");
            }

            $ingredient->decrement('quantity', $requiredQty);

            StockMovement::create([
                'ingredient_id'    => $ingredient->id,
                'type'             => 'out',
                'quantity'         => $requiredQty,
                'unit'             => $ingredient->unit ?? 'kg',
                'unit_cost'        => $ingredient->cost,
                'reference_type'   => 'production',
                'reference_id'     => $recipe->id,
                'reference_number' => $recipe->name,
                'notes'            => "Stock OUT: Consumed for recipe '{$recipe->name}' (batch units: {$quantity})",
                'user_id'          => Auth::id(),
            ]);
        }
    }

    /**
     * Restore ingredients to inventory when an in-progress batch is cancelled or deleted.
     */
    private function restoreIngredients(Recipe $recipe, int $quantity): void
    {
        $recipe->loadMissing('ingredients');

        foreach ($recipe->ingredients as $ing) {
            $returnQty = round((float) $ing->pivot->quantity * $quantity, 2);
            $ingredient = Ingredient::lockForUpdate()->find($ing->id);
            if ($ingredient) {
                $ingredient->increment('quantity', $returnQty);

                StockMovement::create([
                    'ingredient_id'    => $ingredient->id,
                    'type'             => 'in',
                    'quantity'         => $returnQty,
                    'unit'             => $ingredient->unit ?? 'kg',
                    'unit_cost'        => $ingredient->cost,
                    'reference_type'   => 'production_cancellation',
                    'reference_id'     => $recipe->id,
                    'reference_number' => $recipe->name,
                    'notes'            => "Stock IN: Restored from cancelled production recipe '{$recipe->name}'",
                    'user_id'          => Auth::id(),
                ]);
            }
        }
    }

    /**
     * Generate unique sequential batch number: BATCH-YYYYMMDD-XXXX
     */
    private function generateBatchNumber(): string
    {
        $prefix = 'BATCH-' . date('Ymd') . '-';
        $latest = Production::where('batch_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest && preg_match('/-(\d{4})$/', $latest->batch_number, $matches)) {
            $seq = (int) $matches[1] + 1;
        } else {
            $seq = Production::whereDate('created_at', today())->count() + 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
