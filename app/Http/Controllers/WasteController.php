<?php

namespace App\Http\Controllers;

use App\Enums\WasteReason;
use App\Models\Product;
use App\Models\Waste;
use App\Services\WasteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WasteController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        $outletId = current_outlet_id();
        $filters = $request->only(['number', 'product', 'reason']);
        $query = Waste::query()
            ->with(['product.unit', 'unit', 'user', 'outlet'])
            ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        if (filled($filters['number'] ?? null)) {
            $query->where('number', 'like', '%'.$filters['number'].'%');
        }
        if (filled($filters['product'] ?? null)) {
            $query->whereHas('product', function ($product) use ($filters) {
                $product->where(function ($inner) use ($filters) {
                    $inner->where('name', 'like', '%'.$filters['product'].'%')
                        ->orWhere('sku', 'like', '%'.$filters['product'].'%');
                });
            });
        }
        if (filled($filters['reason'] ?? null)) {
            $query->where('reason', $filters['reason']);
        }

        $base = Waste::query()->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        return view('wastes.index', [
            'wastes' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'products' => Product::query()->where('is_stockable', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'stats' => [
                'total' => (clone $base)->count(),
                'today' => (clone $base)->whereDate('created_at', now()->toDateString())->count(),
                'expired' => (clone $base)->where('reason', WasteReason::Expired)->count(),
            ],
        ]);
    }

    public function store(Request $request, WasteService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(WasteReason::class)],
            'notes' => ['nullable', 'string'],
        ], [
            'product_id.required' => 'Produk wajib dipilih.',
            'quantity.required' => 'Kuantitas wajib diisi.',
            'quantity.min' => 'Kuantitas minimal 1.',
            'reason.required' => 'Alasan wajib dipilih.',
        ]);
        $data['outlet_id'] = current_outlet_id();
        $service->record($data);

        return redirect()->route('wastes.index')->with('success', 'Waste tercatat dan stok berkurang.');
    }
}
