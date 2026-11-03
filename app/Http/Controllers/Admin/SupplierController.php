<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount(['ingredients', 'purchaseOrders']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('contact_person', $like, "%{$search}%")
                  ->orWhere('phone', $like, "%{$search}%")
                  ->orWhere('email', $like, "%{$search}%")
                  ->orWhere('address', $like, "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $suppliers = $query->orderBy('id', 'desc')->get();

        $stats = [
            'total'       => Supplier::count(),
            'active'      => Supplier::where('status', 'active')->count(),
            'inactive'    => Supplier::where('status', 'inactive')->count(),
            'total_spend' => (float) PurchaseOrder::where('status', 'received')->sum('total_cost'),
        ];

        return view('admin.suppliers', compact('suppliers', 'stats'));
    }

    public function show(Request $request, Supplier $supplier)
    {
        $supplier->loadMissing([
            'ingredients',
            'purchaseOrders' => function ($q) {
                $q->with('items.ingredient')->orderBy('order_date', 'desc');
            }
        ]);

        $stats = [
            'total_orders'    => $supplier->purchaseOrders->count(),
            'pending_orders'  => $supplier->purchaseOrders->whereIn('status', ['draft', 'ordered'])->count(),
            'received_orders' => $supplier->purchaseOrders->where('status', 'received')->count(),
            'total_spend'     => (float) $supplier->purchaseOrders->where('status', 'received')->sum('total_cost'),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'supplier' => [
                    'id'             => $supplier->id,
                    'name'           => $supplier->name,
                    'contact_person' => $supplier->contact_person,
                    'phone'          => $supplier->phone,
                    'email'          => $supplier->email,
                    'address'        => $supplier->address,
                    'status'         => $supplier->status,
                    'notes'          => $supplier->notes,
                    'created_at'     => $supplier->created_at ? $supplier->created_at->format('M d, Y') : null,
                    'ingredients'    => $supplier->ingredients->map(function ($ing) {
                        return [
                            'id'               => $ing->id,
                            'name'             => $ing->name,
                            'unit'             => $ing->unit,
                            'quantity'         => (float) $ing->quantity,
                            'cost'             => (float) $ing->cost,
                            'minimum_quantity' => (float) $ing->minimum_quantity,
                            'is_low_stock'     => $ing->isLowStock(),
                            'is_out_of_stock'  => $ing->isOutOfStock(),
                        ];
                    }),
                    'purchase_orders' => $supplier->purchaseOrders->take(10)->map(function ($po) {
                        return [
                            'id'                     => $po->id,
                            'po_number'              => $po->po_number,
                            'order_date'             => $po->order_date ? $po->order_date->format('M d, Y') : '—',
                            'expected_delivery_date' => $po->expected_delivery_date ? $po->expected_delivery_date->format('M d, Y') : '—',
                            'status'                 => $po->status,
                            'total_cost'             => (float) $po->total_cost,
                            'items_count'            => $po->items->count(),
                            'url'                    => route('admin.purchase-orders.index', ['open_po' => $po->id]),
                        ];
                    }),
                ],
                'stats' => $stats,
            ]);
        }

        return view('admin.suppliers.show', compact('supplier', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'status'         => 'nullable|string|in:active,inactive',
            'notes'          => 'nullable|string',
        ]);

        Supplier::create([
            'name'           => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'address'        => $validated['address'] ?? null,
            'status'         => $validated['status'] ?? 'active',
            'notes'          => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.suppliers')
            ->with('success', 'Supplier created successfully!');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'status'         => 'nullable|string|in:active,inactive',
            'notes'          => 'nullable|string',
        ]);

        $validated['status'] = $validated['status'] ?? $supplier->status ?? 'active';

        $supplier->update($validated);

        return redirect()->route('admin.suppliers')
            ->with('success', 'Supplier updated successfully!');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', "Cannot delete supplier '{$supplier->name}': Historical purchase orders exist. Retaining records is required for auditing.");
        }

        if ($supplier->ingredients()->exists()) {
            return back()->with('error', "Cannot delete supplier '{$supplier->name}': Raw ingredients are linked to this supplier. Please reassign ingredients first.");
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers')
            ->with('success', 'Supplier deleted successfully!');
    }
}
