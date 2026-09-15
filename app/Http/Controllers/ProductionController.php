<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Enums\ProductionStatus;
use App\Models\Bom;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('production.view'), 403);

        $filters = $request->only(['number', 'product', 'outlet_id', 'status']);
        $query = ProductionOrder::query()
            ->with(['product.unit', 'outlet', 'user', 'bom.product', 'batch', 'items.product.unit', 'items.unit']);

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
        if (filled($filters['outlet_id'] ?? null)) {
            $query->where('outlet_id', $filters['outlet_id']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        $base = ProductionOrder::query();
        $focusId = $request->integer('production') ?: (int) old('_production_id');
        $focusOrder = $focusId
            ? ProductionOrder::query()
                ->with(['product.unit', 'outlet', 'user', 'bom.product', 'batch', 'items.product.unit', 'items.unit'])
                ->find($focusId)
            : null;

        return view('production.index', [
            'orders' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()
                ->whereIn('type', [ProductType::SemiFinished->value, ProductType::Finished->value])
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'boms' => Bom::query()
                ->where('is_active', true)
                ->with('product:id,name')
                ->orderBy('version')
                ->get()
                ->map(fn (Bom $bom) => [
                    'id' => $bom->id,
                    'product_id' => (string) $bom->product_id,
                    'label' => ($bom->product?->name ?? 'BOM').' · v'.$bom->version,
                ])
                ->values(),
            'stats' => [
                'total' => (clone $base)->count(),
                'open' => (clone $base)->whereNotIn('status', [
                    ProductionStatus::Completed->value,
                    ProductionStatus::Cancelled->value,
                ])->count(),
                'completed' => (clone $base)->where('status', ProductionStatus::Completed)->count(),
            ],
            'focusPayload' => $focusOrder?->toModalArray(),
        ]);
    }

    public function create(): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.manage'), 403);

        return redirect()->route('production.index', ['modal' => 'create']);
    }

    public function store(Request $request, ProductionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'product_id' => ['required', 'exists:products,id'],
            'bom_id' => ['nullable', 'exists:boms,id'],
            'quantity_planned' => ['required', 'numeric', 'min:0.001'],
            'production_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'outlet_id.required' => 'Outlet wajib dipilih.',
            'product_id.required' => 'Produk wajib dipilih.',
            'quantity_planned.required' => 'Kuantitas rencana wajib diisi.',
            'quantity_planned.min' => 'Kuantitas rencana minimal 0.001.',
        ]);
        $order = $service->create($data);

        return redirect()
            ->route('production.index', ['modal' => 'view', 'production' => $order->id])
            ->with('success', 'Produksi dibuat.');
    }

    public function show(ProductionOrder $production): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.view'), 403);

        return redirect()->route('production.index', ['modal' => 'view', 'production' => $production->id]);
    }

    public function start(ProductionOrder $production, ProductionService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.manage'), 403);
        $service->start($production);

        return redirect()
            ->route('production.index', ['modal' => 'view', 'production' => $production->id])
            ->with('success', 'Produksi dimulai.');
    }

    public function complete(Request $request, ProductionOrder $production, ProductionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $request->validate([
            'quantity_produced' => ['required', 'numeric', 'min:0.001'],
        ], [
            'quantity_produced.required' => 'Kuantitas hasil wajib diisi.',
            'quantity_produced.min' => 'Kuantitas hasil minimal 0.001.',
        ]);
        $service->complete($production, (float) $data['quantity_produced']);

        return redirect()
            ->route('production.index', ['modal' => 'view', 'production' => $production->id])
            ->with('success', 'Produksi selesai. Stok dan batch sudah tercatat.');
    }

    public function cancel(Request $request, ProductionOrder $production, ProductionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);
        $service->cancel($production, $data['reason']);

        return redirect()
            ->route('production.index', ['modal' => 'view', 'production' => $production->id])
            ->with('success', 'Produksi dibatalkan.');
    }
}
