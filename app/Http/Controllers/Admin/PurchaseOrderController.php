<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'items.ingredient', 'user']);

        // 1. Search Filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('po_number', $like, "%{$search}%")
                  ->orWhere('notes', $like, "%{$search}%")
                  ->orWhereHas('supplier', function ($s) use ($search, $like) {
                      $s->where('name', $like, "%{$search}%");
                  });
            });
        }

        // 2. Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtolower($request->status));
        }

        // 3. Supplier Filter
        if ($request->filled('supplier_id') && $request->supplier_id !== 'all') {
            $query->where('supplier_id', $request->supplier_id);
        }

        // 4. Date Filter
        if ($request->filled('date')) {
            $date = Carbon::parse($request->date)->toDateString();
            $query->whereDate('order_date', $date);
        }

        $purchaseOrders = $query->orderBy('id', 'desc')->get();

        $stats = [
            'total'       => PurchaseOrder::count(),
            'draft'       => PurchaseOrder::where('status', 'draft')->count(),
            'ordered'     => PurchaseOrder::where('status', 'ordered')->count(),
            'received'    => PurchaseOrder::where('status', 'received')->count(),
            'cancelled'   => PurchaseOrder::where('status', 'cancelled')->count(),
            'total_spend' => (float) PurchaseOrder::where('status', 'received')->sum('total_cost'),
        ];

        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get();
        $ingredients = Ingredient::where('status', 'active')->with('supplier')->orderBy('name')->get();

        $ingredientsJson = $ingredients->mapWithKeys(function ($ing) {
            return [$ing->id => [
                'id'          => $ing->id,
                'name'        => $ing->name,
                'unit'        => $ing->unit,
                'cost'        => (float) $ing->cost,
                'stock'       => (float) $ing->quantity,
                'supplier_id' => $ing->supplier_id,
            ]];
        });

        return view('admin.purchase_orders.index', compact('purchaseOrders', 'stats', 'suppliers', 'ingredients', 'ingredientsJson'));
    }

    public function create(Request $request)
    {
        return redirect()->route('admin.purchase-orders.index', array_merge($request->query(), ['action' => 'create']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id'            => 'required|exists:suppliers,id',
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes'                  => 'nullable|string|max:2000',
            'items'                  => 'required|array|min:1',
            'items.*.ingredient_id'  => 'required|exists:ingredients,id',
            'items.*.quantity'       => 'required|numeric|gt:0',
            'items.*.purchase_price' => 'required|numeric|gte:0',
        ]);

        try {
            $po = DB::transaction(function () use ($validated) {
                $poNumber = $this->generatePoNumber();

                $purchaseOrder = PurchaseOrder::create([
                    'po_number'              => $poNumber,
                    'supplier_id'            => $validated['supplier_id'],
                    'order_date'             => $validated['order_date'],
                    'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                    'status'                 => 'draft',
                    'notes'                  => $validated['notes'] ?? null,
                    'user_id'                => Auth::id(),
                    'total_cost'             => 0.00,
                ]);

                $totalCost = 0.00;

                foreach ($validated['items'] as $itemData) {
                    $ingredient = Ingredient::findOrFail($itemData['ingredient_id']);
                    $qty = round((float) $itemData['quantity'], 2);
                    $price = round((float) $itemData['purchase_price'], 2);
                    $subtotal = round($qty * $price, 2);

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'ingredient_id'     => $ingredient->id,
                        'quantity'          => $qty,
                        'unit'              => $ingredient->unit ?? 'kg',
                        'purchase_price'    => $price,
                        'subtotal'          => $subtotal,
                    ]);

                    $totalCost += $subtotal;
                }

                $purchaseOrder->update(['total_cost' => $totalCost]);

                return $purchaseOrder;
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase Order #{$po->po_number} created successfully!",
                    'purchase_order' => $po,
                ]);
            }

            if ($request->filled('redirect_to') && $request->redirect_to === 'index') {
                return redirect()->route('admin.purchase-orders.index')
                    ->with('success', "Purchase Order #{$po->po_number} created successfully!");
            }

            return redirect()->route('admin.purchase-orders.index', ['open_po' => $po->id])
                ->with('success', "Purchase Order #{$po->po_number} created successfully!");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create Purchase Order: ' . $e->getMessage());
        }
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing(['supplier', 'items.ingredient', 'user']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'purchase_order' => [
                    'id'                         => $purchaseOrder->id,
                    'po_number'                  => $purchaseOrder->po_number,
                    'status'                     => $purchaseOrder->status,
                    'total_cost'                 => (float) $purchaseOrder->total_cost,
                    'supplier_id'                => $purchaseOrder->supplier_id,
                    'order_date'                 => $purchaseOrder->order_date ? $purchaseOrder->order_date->format('M d, Y') : '—',
                    'order_date_raw'             => $purchaseOrder->order_date ? $purchaseOrder->order_date->format('Y-m-d') : '',
                    'expected_delivery_date'     => $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('M d, Y') : 'Flexible / Unspecified',
                    'expected_delivery_date_raw' => $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('Y-m-d') : '',
                    'received_date'              => $purchaseOrder->received_date ? $purchaseOrder->received_date->format('M d, Y H:i') : null,
                    'created_at'                 => $purchaseOrder->created_at ? $purchaseOrder->created_at->format('M d, Y H:i') : null,
                    'notes'                      => $purchaseOrder->notes,
                    'creator_name'               => $purchaseOrder->user->name ?? 'System Admin',
                    'supplier'                   => $purchaseOrder->supplier ? [
                        'id'             => $purchaseOrder->supplier->id,
                        'name'           => $purchaseOrder->supplier->name,
                        'contact_person' => $purchaseOrder->supplier->contact_person,
                        'phone'          => $purchaseOrder->supplier->phone,
                        'email'          => $purchaseOrder->supplier->email,
                        'address'        => $purchaseOrder->supplier->address,
                    ] : null,
                    'items'                      => $purchaseOrder->items->map(function ($item) {
                        return [
                            'ingredient_id'  => $item->ingredient_id,
                            'name'           => $item->ingredient->name ?? 'Ingredient',
                            'current_stock'  => $item->ingredient ? (float) $item->ingredient->quantity : 0,
                            'quantity'       => (float) $item->quantity,
                            'unit'           => $item->unit,
                            'purchase_price' => (float) $item->purchase_price,
                            'subtotal'       => (float) $item->subtotal,
                        ];
                    }),
                    'routes'                     => [
                        'edit'    => route('admin.purchase-orders.edit', $purchaseOrder->id),
                        'update'  => route('admin.purchase-orders.update', $purchaseOrder->id),
                        'order'   => route('admin.purchase-orders.order', $purchaseOrder->id),
                        'receive' => route('admin.purchase-orders.receive', $purchaseOrder->id),
                        'cancel'  => route('admin.purchase-orders.cancel', $purchaseOrder->id),
                    ],
                ]
            ]);
        }

        return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->isDraft()) {
            return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id])
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $purchaseOrder->loadMissing(['items.ingredient', 'supplier']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'purchase_order' => [
                    'id'                         => $purchaseOrder->id,
                    'po_number'                  => $purchaseOrder->po_number,
                    'supplier_id'                => $purchaseOrder->supplier_id,
                    'order_date'                 => $purchaseOrder->order_date ? $purchaseOrder->order_date->format('M d, Y') : '—',
                    'order_date_raw'             => $purchaseOrder->order_date ? $purchaseOrder->order_date->format('Y-m-d') : '',
                    'expected_delivery_date'     => $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('M d, Y') : '',
                    'expected_delivery_date_raw' => $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('Y-m-d') : '',
                    'notes'                      => $purchaseOrder->notes,
                    'supplier'                   => $purchaseOrder->supplier ? [
                        'id'   => $purchaseOrder->supplier->id,
                        'name' => $purchaseOrder->supplier->name,
                    ] : null,
                    'items'                      => $purchaseOrder->items->map(function ($item) {
                        return [
                            'ingredient_id'  => $item->ingredient_id,
                            'name'           => $item->ingredient->name ?? 'Ingredient',
                            'quantity'       => (float) $item->quantity,
                            'purchase_price' => (float) $item->purchase_price,
                            'unit'           => $item->unit,
                            'subtotal'       => (float) $item->subtotal,
                        ];
                    })->values()->all(),
                    'routes'                     => [
                        'update' => route('admin.purchase-orders.update', $purchaseOrder->id),
                    ]
                ]
            ]);
        }

        return redirect()->route('admin.purchase-orders.index', ['edit_po' => $purchaseOrder->id]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->isDraft()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only draft purchase orders can be updated.'], 422);
            }
            return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id])
                ->with('error', 'Only draft purchase orders can be updated.');
        }

        $validated = $request->validate([
            'supplier_id'            => 'required|exists:suppliers,id',
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes'                  => 'nullable|string|max:2000',
            'items'                  => 'required|array|min:1',
            'items.*.ingredient_id'  => 'required|exists:ingredients,id',
            'items.*.quantity'       => 'required|numeric|gt:0',
            'items.*.purchase_price' => 'required|numeric|gte:0',
        ]);

        try {
            DB::transaction(function () use ($purchaseOrder, $validated) {
                $purchaseOrder->items()->delete();

                $totalCost = 0.00;

                foreach ($validated['items'] as $itemData) {
                    $ingredient = Ingredient::findOrFail($itemData['ingredient_id']);
                    $qty = round((float) $itemData['quantity'], 2);
                    $price = round((float) $itemData['purchase_price'], 2);
                    $subtotal = round($qty * $price, 2);

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'ingredient_id'     => $ingredient->id,
                        'quantity'          => $qty,
                        'unit'              => $ingredient->unit ?? 'kg',
                        'purchase_price'    => $price,
                        'subtotal'          => $subtotal,
                    ]);

                    $totalCost += $subtotal;
                }

                $purchaseOrder->update([
                    'supplier_id'            => $validated['supplier_id'],
                    'order_date'             => $validated['order_date'],
                    'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                    'notes'                  => $validated['notes'] ?? null,
                    'total_cost'             => $totalCost,
                ]);
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase Order #{$purchaseOrder->po_number} updated successfully!",
                    'purchase_order' => $purchaseOrder->fresh(['supplier', 'items.ingredient']),
                ]);
            }

            return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id])
                ->with('success', "Purchase Order #{$purchaseOrder->po_number} updated successfully!");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update Purchase Order: ' . $e->getMessage());
        }
    }

    public function order(PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canTransitionTo('ordered')) {
            return back()->with('error', "Invalid transition: Cannot change status from {$purchaseOrder->status} to ordered.");
        }

        $purchaseOrder->update(['status' => 'ordered']);

        // Dispatch notification for ordered PO
        try {
            app(\App\Services\NotificationService::class)->notifyPurchaseOrderUpdated($purchaseOrder, 'ordered');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to dispatch PO ordered notification: " . $e->getMessage());
        }

        return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id])
            ->with('success', "Purchase Order #{$purchaseOrder->po_number} marked as Ordered. Awaiting vendor delivery.");
    }

    public function receive(PurchaseOrder $purchaseOrder)
    {
        // 1. Validate status transition
        if (!$purchaseOrder->canTransitionTo('received')) {
            return back()->with('error', "Invalid transition: Cannot receive Purchase Order #{$purchaseOrder->po_number} because its current status is '{$purchaseOrder->status}'. Only 'ordered' POs can be received.");
        }

        try {
            DB::transaction(function () use ($purchaseOrder) {
                // Pessimistic lock on purchase order to prevent double-receiving
                $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);

                if ($po->status !== 'ordered') {
                    throw new \Exception("Duplicate receiving rejected: Purchase Order #{$po->po_number} has status '{$po->status}'. Stock was already updated or order is cancelled.");
                }

                $po->loadMissing('items.ingredient');

                if ($po->items->isEmpty()) {
                    throw new \Exception("Purchase Order #{$po->po_number} has no line items to receive.");
                }

                foreach ($po->items as $item) {
                    if (!$item->ingredient_id) {
                        continue;
                    }

                    // Pessimistic lock on ingredient row
                    $ingredient = Ingredient::lockForUpdate()->findOrFail($item->ingredient_id);

                    // 1. Stock In: increment quantity
                    $ingredient->increment('quantity', $item->quantity);

                    // 2. Update purchase cost with new unit price
                    $ingredient->update(['cost' => $item->purchase_price]);

                    // 3. Link ingredient to supplier if currently unassigned
                    if (!$ingredient->supplier_id && $po->supplier_id) {
                        $ingredient->update(['supplier_id' => $po->supplier_id]);
                    }

                    // 4. Log audit stock movement
                    StockMovement::create([
                        'ingredient_id'    => $ingredient->id,
                        'type'             => 'in',
                        'quantity'         => $item->quantity,
                        'unit'             => $item->unit ?? $ingredient->unit,
                        'unit_cost'        => $item->purchase_price,
                        'reference_type'   => 'purchase_order',
                        'reference_id'     => $po->id,
                        'reference_number' => $po->po_number,
                        'notes'            => "Received Purchase Order #{$po->po_number} from {$po->supplier->name}",
                        'user_id'          => Auth::id(),
                    ]);
                }

                // 5. Update PO status and received timestamp
                $po->status = 'received';
                $po->received_date = now();
                $po->save();
            });

            // Dispatch notification for received PO and auto-resolve low-stock alerts
            try {
                $freshPo = PurchaseOrder::find($purchaseOrder->id);
                if ($freshPo) {
                    app(\App\Services\NotificationService::class)->notifyPurchaseOrderUpdated($freshPo, 'received');
                }
                app(\App\Services\NotificationService::class)->syncConditionAlerts();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch PO received notification: " . $e->getMessage());
            }

            return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id])
                ->with('success', "Purchase Order #{$purchaseOrder->po_number} received successfully! Raw material inventory and purchase costs updated.");
        } catch (\Exception $e) {
            return back()->with('error', 'Receiving failed: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canTransitionTo('cancelled')) {
            return back()->with('error', "Invalid transition: Cannot cancel Purchase Order #{$purchaseOrder->po_number} with status '{$purchaseOrder->status}'.");
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return redirect()->route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id])
            ->with('success', "Purchase Order #{$purchaseOrder->po_number} has been cancelled.");
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . date('Ymd') . '-';
        $latest = PurchaseOrder::where('po_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest && preg_match('/-(\d{4})$/', $latest->po_number, $matches)) {
            $seq = (int) $matches[1] + 1;
        } else {
            $seq = PurchaseOrder::whereDate('created_at', today())->count() + 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
