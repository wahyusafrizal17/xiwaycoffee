<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\ProductionBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('production.view'), 403);

        $filters = $request->only(['number', 'product', 'outlet_id', 'status']);
        $query = ProductionBatch::query()
            ->with(['product.unit', 'outlet', 'destinationOutlet', 'productionOrder.items.product.unit', 'salesItems.order', 'salesItems.product']);

        if (filled($filters['number'] ?? null)) {
            $query->where('batch_number', 'like', '%'.$filters['number'].'%');
        }
        if (filled($filters['product'] ?? null)) {
            $query->whereHas('product', function ($product) use ($filters) {
                $product->where(function ($inner) use ($filters) {
                    $inner->where('name', 'like', '%'.$filters['product'].'%')
                        ->orWhere('sku', 'like', '%'.$filters['product'].'%');
                });
            });
        }
        if (filled($filters['outlet_id'] ?? null)) {
            $query->where('outlet_id', $filters['outlet_id']);
        }

        $today = now()->toDateString();
        if (($filters['status'] ?? '') === 'available') {
            $query->where('remaining_quantity', '>', 0)
                ->where(function ($inner) use ($today) {
                    $inner->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today);
                });
        } elseif (($filters['status'] ?? '') === 'expired') {
            $query->whereDate('expires_at', '<', $today);
        } elseif (($filters['status'] ?? '') === 'depleted') {
            $query->where('remaining_quantity', '<=', 0);
        }

        $base = ProductionBatch::query();
        $focusId = $request->integer('batch');
        $focusBatch = $focusId
            ? ProductionBatch::query()
                ->with(['product.unit', 'outlet', 'destinationOutlet', 'productionOrder.items.product.unit', 'salesItems.order', 'salesItems.product'])
                ->find($focusId)
            : null;

        return view('batches.index', [
            'batches' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => (clone $base)->count(),
                'available' => (clone $base)
                    ->where('remaining_quantity', '>', 0)
                    ->where(function ($inner) use ($today) {
                        $inner->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today);
                    })
                    ->count(),
                'expired' => (clone $base)->whereDate('expires_at', '<', $today)->count(),
            ],
            'focusPayload' => $focusBatch?->toModalArray(),
        ]);
    }

    public function show(ProductionBatch $batch): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.view'), 403);

        return redirect()->route('batches.index', ['modal' => 'view', 'batch' => $batch->id]);
    }
}
