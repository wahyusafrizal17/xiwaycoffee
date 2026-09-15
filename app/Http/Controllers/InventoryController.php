<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        $outletId = current_outlet_id();
        $filters = $request->only(['sku', 'name', 'category_id', 'status']);
        $query = Inventory::query()
            ->with(['product.unit', 'product.category'])
            ->where('outlet_id', $outletId)
            ->whereHas('product');

        if (filled($filters['sku'] ?? null)) {
            $query->whereHas('product', fn ($product) => $product->where('sku', 'like', '%'.$filters['sku'].'%'));
        }
        if (filled($filters['name'] ?? null)) {
            $query->whereHas('product', fn ($product) => $product->where('name', 'like', '%'.$filters['name'].'%'));
        }
        if (filled($filters['category_id'] ?? null)) {
            $query->whereHas('product', fn ($product) => $product->where('category_id', $filters['category_id']));
        }
        if (($filters['status'] ?? '') === 'out') {
            $query->where('quantity', '<=', 0);
        } elseif (($filters['status'] ?? '') === 'low') {
            $query->where('quantity', '>', 0)
                ->whereHas('product', function ($product) {
                    $product->where('reorder_level', '>', 0)
                        ->whereColumn('inventories.quantity', '<=', 'products.reorder_level');
                });
        } elseif (($filters['status'] ?? '') === 'ok') {
            $query->where('quantity', '>', 0)
                ->whereHas('product', function ($product) {
                    $product->where(function ($inner) {
                        $inner->where('reorder_level', '<=', 0)
                            ->orWhereColumn('inventories.quantity', '>', 'products.reorder_level');
                    });
                });
        }

        $base = Inventory::query()->where('outlet_id', $outletId);

        return view('inventory.index', [
            'stocks' => $query
                ->orderBy(
                    Product::query()->select('name')->whereColumn('products.id', 'inventories.product_id')->limit(1)
                )
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('is_stockable', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'stats' => [
                'total' => (clone $base)->count(),
                'low' => (clone $base)
                    ->where('quantity', '>', 0)
                    ->whereHas('product', function ($product) {
                        $product->where('reorder_level', '>', 0)
                            ->whereColumn('inventories.quantity', '<=', 'products.reorder_level');
                    })
                    ->count(),
                'out' => (clone $base)->where('quantity', '<=', 0)->count(),
            ],
        ]);
    }

    public function movements(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        $outletId = current_outlet_id();
        $filters = $request->only(['reference', 'product', 'type']);
        $query = InventoryMovement::query()
            ->with(['product.unit', 'unit', 'user', 'outlet'])
            ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        if (filled($filters['reference'] ?? null)) {
            $query->where('reference_number', 'like', '%'.$filters['reference'].'%');
        }
        if (filled($filters['product'] ?? null)) {
            $query->whereHas('product', function ($product) use ($filters) {
                $product->where(function ($inner) use ($filters) {
                    $inner->where('name', 'like', '%'.$filters['product'].'%')
                        ->orWhere('sku', 'like', '%'.$filters['product'].'%');
                });
            });
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }

        $base = InventoryMovement::query()->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        return view('inventory.movements', [
            'movements' => $query
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => (clone $base)->count(),
                'in' => (clone $base)->where('quantity', '>', 0)->count(),
                'out' => (clone $base)->where('quantity', '<', 0)->count(),
            ],
        ]);
    }

    public function adjust(Request $request, InventoryService $inventory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'product_id.required' => 'Produk wajib dipilih.',
            'quantity.required' => 'Kuantitas wajib diisi.',
            'reason.required' => 'Alasan wajib diisi.',
        ]);

        $inventory->adjust(
            current_outlet_id(),
            (int) $data['product_id'],
            (float) $data['quantity'],
            \App\Enums\StockMovementType::Adjustment,
            $data['reason'],
            null,
            null,
            true,
        );

        return redirect()->route('inventory.index')->with('success', 'Stok disesuaikan.');
    }
}
