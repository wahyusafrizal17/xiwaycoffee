<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BomController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('production.view'), 403);

        $filters = $request->only(['product', 'version', 'status']);
        $query = Bom::query()->with(['product', 'items.component.unit', 'items.unit']);

        if (filled($filters['product'] ?? null)) {
            $query->whereHas('product', function ($product) use ($filters) {
                $product->where(function ($inner) use ($filters) {
                    $inner->where('name', 'like', '%'.$filters['product'].'%')
                        ->orWhere('sku', 'like', '%'.$filters['product'].'%');
                });
            });
        }
        if (filled($filters['version'] ?? null)) {
            $query->where('version', 'like', '%'.$filters['version'].'%');
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $base = Bom::query();
        $focusId = $request->integer('bom') ?: (int) old('_bom_id');
        $focusBom = $focusId
            ? Bom::query()->with(['product', 'items.component.unit', 'items.unit'])->find($focusId)
            : null;

        return view('boms.index', [
            'boms' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'stats' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('is_active', true)->count(),
                'inactive' => (clone $base)->where('is_active', false)->count(),
            ],
            'focusPayload' => $focusBom?->toModalArray(),
        ]);
    }

    public function create(): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.manage'), 403);

        return redirect()->route('boms.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $this->validated($request);
        $items = $data['items'];
        unset($data['items']);
        $bom = Bom::query()->create($data);
        $this->syncItems($bom, $items);
        $bom->applyToProduct();

        return redirect()
            ->route('boms.index', ['modal' => 'view', 'bom' => $bom->id])
            ->with('success', 'BOM dibuat.');
    }

    public function show(Bom $bom): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.view'), 403);

        return redirect()->route('boms.index', ['modal' => 'view', 'bom' => $bom->id]);
    }

    public function edit(Bom $bom): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('production.manage'), 403);

        return redirect()->route('boms.index', ['modal' => 'edit', 'bom' => $bom->id]);
    }

    public function update(Request $request, Bom $bom): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('production.manage'), 403);
        $data = $this->validated($request);
        $items = $data['items'];
        unset($data['items']);
        $bom->update($data);
        $bom->items()->delete();
        $this->syncItems($bom, $items);
        $bom->applyToProduct();

        return redirect()
            ->route('boms.index', ['modal' => 'view', 'bom' => $bom->id])
            ->with('success', 'BOM diperbarui.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'version' => ['required', 'string', 'max:20'],
            'yield_percentage' => ['required', 'numeric', 'min:1', 'max:200'],
            'waste_percentage' => ['nullable', 'numeric', 'min:0'],
            'active_from' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_id' => ['required', 'exists:products,id'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.waste_percentage' => ['nullable', 'numeric', 'min:0'],
            'items.*.yield_percentage' => ['nullable', 'numeric', 'min:1'],
        ], [
            'product_id.required' => 'Produk jadi wajib dipilih.',
            'version.required' => 'Versi wajib diisi.',
            'yield_percentage.required' => 'Yield wajib diisi.',
            'yield_percentage.min' => 'Yield minimal 1%.',
            'yield_percentage.max' => 'Yield maksimal 200%.',
            'active_until.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'items.required' => 'Minimal satu komponen.',
            'items.min' => 'Minimal satu komponen.',
            'items.*.component_id.required' => 'Komponen wajib dipilih.',
            'items.*.quantity.required' => 'Kuantitas komponen wajib diisi.',
            'items.*.quantity.min' => 'Kuantitas komponen minimal 0.0001.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncItems(Bom $bom, array $items): void
    {
        foreach ($items as $item) {
            $bom->items()->create([
                'component_id' => $item['component_id'],
                'unit_id' => $item['unit_id'] ?? null,
                'quantity' => $item['quantity'],
                'waste_percentage' => $item['waste_percentage'] ?? 0,
                'yield_percentage' => $item['yield_percentage'] ?? 100,
            ]);
        }
    }
}
