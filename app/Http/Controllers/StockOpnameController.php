<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        $outletId = current_outlet_id();
        $filters = $request->only(['number', 'category_id', 'status']);
        $query = StockOpname::query()
            ->with(['outlet', 'creator', 'approver', 'category', 'items.product.unit'])
            ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        if (filled($filters['number'] ?? null)) {
            $query->where('number', 'like', '%'.$filters['number'].'%');
        }
        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', $filters['category_id']);
        }
        if (($filters['status'] ?? '') === 'draft') {
            $query->where('status', 'draft');
        } elseif (($filters['status'] ?? '') === 'finalized') {
            $query->where('status', 'finalized');
        }

        $base = StockOpname::query()->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id));

        $focusId = $request->integer('opname') ?: (int) old('_opname_id');
        $focusOpname = $focusId
            ? StockOpname::query()
                ->with(['outlet', 'creator', 'approver', 'category', 'items.product.unit'])
                ->when($outletId, fn ($q, $id) => $q->where('outlet_id', $id))
                ->find($focusId)
            : null;

        return view('opname.index', [
            'opnames' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'finalized' => (clone $base)->where('status', 'finalized')->count(),
            ],
            'focusPayload' => $focusOpname?->toModalArray(),
        ]);
    }

    public function store(Request $request, StockOpnameService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'notes' => ['nullable', 'string'],
        ], [
            'category_id.exists' => 'Kategori tidak valid.',
        ]);
        $data['outlet_id'] = current_outlet_id();
        $opname = $service->create($data);

        return redirect()
            ->route('opnames.index', ['modal' => 'count', 'opname' => $opname->id])
            ->with('success', 'Stock opname dibuat.');
    }

    public function show(StockOpname $opname): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.view'), 403);

        return redirect()->route('opnames.index', ['modal' => 'view', 'opname' => $opname->id]);
    }

    public function update(Request $request, StockOpname $opname, StockOpnameService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.physical_qty' => ['required', 'numeric'],
            'items.*.reason' => ['nullable', 'string'],
        ], [
            'items.required' => 'Item opname wajib diisi.',
            'items.*.physical_qty.required' => 'Kuantitas fisik wajib diisi.',
            'items.*.physical_qty.numeric' => 'Kuantitas fisik harus berupa angka.',
        ]);
        $service->updateItems($opname, $data['items']);

        return redirect()
            ->route('opnames.index', ['modal' => 'count', 'opname' => $opname->id])
            ->with('success', 'Perhitungan fisik disimpan.');
    }

    public function finalize(StockOpname $opname, StockOpnameService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.approve'), 403);
        $service->finalize($opname);

        return redirect()
            ->route('opnames.index', ['modal' => 'view', 'opname' => $opname->id])
            ->with('success', 'Stock opname difinalisasi. Adjustment sudah dibuat.');
    }
}
