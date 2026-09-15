<?php

namespace App\Http\Controllers;

use App\Enums\TransferStatus;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        $outletId = current_outlet_id();
        $filters = $request->only(['number', 'source_outlet_id', 'destination_outlet_id', 'status']);
        $query = StockTransfer::query()
            ->with(['sourceOutlet', 'destinationOutlet', 'requester', 'approver', 'receiver', 'items.product.unit'])
            ->when($outletId, function ($q, $id) {
                $q->where(function ($inner) use ($id) {
                    $inner->where('source_outlet_id', $id)->orWhere('destination_outlet_id', $id);
                });
            });

        if (filled($filters['number'] ?? null)) {
            $query->where('number', 'like', '%'.$filters['number'].'%');
        }
        if (filled($filters['source_outlet_id'] ?? null)) {
            $query->where('source_outlet_id', $filters['source_outlet_id']);
        }
        if (filled($filters['destination_outlet_id'] ?? null)) {
            $query->where('destination_outlet_id', $filters['destination_outlet_id']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        $base = StockTransfer::query()->when($outletId, function ($q, $id) {
            $q->where(function ($inner) use ($id) {
                $inner->where('source_outlet_id', $id)->orWhere('destination_outlet_id', $id);
            });
        });

        $focusId = $request->integer('transfer') ?: (int) old('_transfer_id');
        $focusTransfer = $focusId
            ? (clone $base)->with(['sourceOutlet', 'destinationOutlet', 'requester', 'approver', 'receiver', 'items.product.unit'])->find($focusId)
            : null;

        return view('transfers.index', [
            'transfers' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('is_stockable', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'stats' => [
                'total' => (clone $base)->count(),
                'open' => (clone $base)->whereNotIn('status', [
                    TransferStatus::Completed->value,
                    TransferStatus::Cancelled->value,
                ])->count(),
                'completed' => (clone $base)->where('status', TransferStatus::Completed)->count(),
            ],
            'focusPayload' => $focusTransfer?->toModalArray(),
        ]);
    }

    public function create(): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.manage'), 403);

        return redirect()->route('transfers.index', ['modal' => 'create']);
    }

    public function store(Request $request, StockTransferService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);
        $data = $request->validate([
            'source_outlet_id' => ['required', 'exists:outlets,id'],
            'destination_outlet_id' => ['required', 'exists:outlets,id', 'different:source_outlet_id'],
            'transfer_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ], [
            'source_outlet_id.required' => 'Outlet sumber wajib dipilih.',
            'destination_outlet_id.required' => 'Outlet tujuan wajib dipilih.',
            'destination_outlet_id.different' => 'Outlet tujuan harus berbeda.',
            'items.required' => 'Minimal satu item wajib diisi.',
            'items.min' => 'Minimal satu item wajib diisi.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.quantity.min' => 'Kuantitas minimal 0.001.',
        ]);
        $transfer = $service->create($data);

        return redirect()
            ->route('transfers.index', ['modal' => 'view', 'transfer' => $transfer->id])
            ->with('success', 'Transfer dibuat.');
    }

    public function show(StockTransfer $transfer): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.view'), 403);

        return redirect()->route('transfers.index', ['modal' => 'view', 'transfer' => $transfer->id]);
    }

    public function request(StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.manage'), 403);
        $service->request($transfer);

        return redirect()
            ->route('transfers.index', ['modal' => 'view', 'transfer' => $transfer->id])
            ->with('success', 'Transfer diajukan.');
    }

    public function approve(StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.approve'), 403);
        $service->approve($transfer);

        return redirect()
            ->route('transfers.index', ['modal' => 'view', 'transfer' => $transfer->id])
            ->with('success', 'Transfer disetujui.');
    }

    public function ship(StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('inventory.manage'), 403);
        $service->ship($transfer);

        return redirect()
            ->route('transfers.index', ['modal' => 'view', 'transfer' => $transfer->id])
            ->with('success', 'Transfer dikirim. Stok sumber berkurang.');
    }

    public function receive(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);
        $data = $request->validate([
            'received' => ['nullable', 'array'],
            'received.*' => ['numeric', 'min:0'],
        ], [
            'received.*.numeric' => 'Kuantitas diterima harus berupa angka.',
            'received.*.min' => 'Kuantitas diterima tidak boleh negatif.',
        ]);
        $service->receive($transfer, $data['received'] ?? []);

        return redirect()
            ->route('transfers.index', ['modal' => 'view', 'transfer' => $transfer->id])
            ->with('success', 'Transfer diterima. Stok tujuan bertambah.');
    }
}
