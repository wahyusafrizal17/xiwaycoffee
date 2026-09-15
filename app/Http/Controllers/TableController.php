<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DiningTable;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tables.view'), 403);

        $outletId = current_outlet_id();
        $tables = DiningTable::query()
            ->with([
                'activeSession',
                'reservations' => fn ($q) => $q->where('status', 'reserved'),
                'orders' => fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled'])->withCount('items')->latest('id'),
            ])
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->get()
            ->each(fn (DiningTable $table) => $table->setAttribute(
                'open_minutes',
                $table->activeSession?->durationMinutes()
            ));

        return view('tables.index', [
            'tables' => $tables,
            'reservations' => $tables
                ->flatMap(fn (DiningTable $table) => $table->reservations->each(fn ($reservation) => $reservation->setRelation('table', $table)))
                ->sortBy('reserved_at')
                ->values(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tables.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1'],
            'shape' => ['nullable', 'in:square,round,rect'],
            'zone' => ['nullable', 'string', 'max:50'],
            'pos_x' => ['nullable', 'integer'],
            'pos_y' => ['nullable', 'integer'],
        ]);

        $data['outlet_id'] = current_outlet_id();
        DiningTable::query()->create($data);

        return back()->with('success', 'Meja ditambahkan.');
    }

    public function update(Request $request, DiningTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tables.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1'],
            'shape' => ['nullable', 'in:square,round,rect'],
            'zone' => ['nullable', 'string', 'max:50'],
        ]);

        $table->update($data);

        return back()->with('success', 'Meja diperbarui.');
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('tables.manage'), 403);
        $table->delete();

        return back()->with('success', 'Meja dihapus.');
    }

    public function move(Request $request, DiningTable $table, TableService $tables): JsonResponse
    {
        $data = $request->validate([
            'pos_x' => ['required', 'integer', 'min:0'],
            'pos_y' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json($tables->move($table->id, $data['pos_x'], $data['pos_y']));
    }

    public function transfer(Request $request, TableService $tables): RedirectResponse
    {
        $data = $request->validate([
            'from_id' => ['required', 'exists:tables,id'],
            'to_id' => ['required', 'exists:tables,id', 'different:from_id'],
        ]);
        $tables->transfer((int) $data['from_id'], (int) $data['to_id']);

        return back()->with('success', 'Order dipindahkan ke meja lain.');
    }

    public function merge(Request $request, TableService $tables): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'exists:tables,id'],
            'target_id' => ['required', 'exists:tables,id', 'different:source_id'],
        ]);
        $source = DiningTable::query()->find($data['source_id']);
        $target = DiningTable::query()->find($data['target_id']);
        $tables->merge((int) $data['source_id'], (int) $data['target_id']);

        return back()->with('success', ($source?->code ?? 'Meja sumber').' digabung ke '.($target?->code ?? 'meja target').'. '.($source?->code ?? 'Meja sumber').' sekarang kosong.');
    }

    public function split(Request $request, TableService $tables): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tables.manage'), 403);

        $data = $request->validate([
            'source_id' => ['required', 'exists:tables,id'],
            'target_id' => ['required', 'exists:tables,id', 'different:source_id'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:order_items,id'],
        ]);
        $tables->split((int) $data['source_id'], (int) $data['target_id'], $data['item_ids']);

        return back()->with('success', 'Meja dipisah.');
    }

    public function live(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('tables.view'), 403);

        $tables = DiningTable::query()
            ->with(['orders' => fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled'])->with('items')->latest('id')])
            ->where('outlet_id', current_outlet_id())
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (DiningTable $table) {
                $order = $table->orders->first();

                return [
                    'id' => $table->id,
                    'code' => $table->code,
                    'status' => $table->status?->value,
                    'label' => $table->status?->label(),
                    'order_id' => $order?->id,
                    'items' => $order?->items->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'quantity' => (float) $item->quantity,
                    ])->values() ?? [],
                ];
            });

        return response()->json([
            'tables' => $tables,
            'counts' => [
                'available' => $tables->where('status', 'available')->count(),
                'occupied' => $tables->where('status', 'occupied')->count(),
                'reserved' => $tables->where('status', 'reserved')->count(),
            ],
        ]);
    }

    public function reserve(Request $request, TableService $tables): RedirectResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'exists:tables,id'],
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'reserved_at' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['outlet_id'] = current_outlet_id();
        $tables->reserve($data);

        return back()->with('success', 'Reservasi tersimpan.');
    }

    public function show(DiningTable $table): View
    {
        return view('tables.show', [
            'table' => $table->load(['outlet', 'sessions.order', 'reservations']),
            'order' => $table->currentOrder()?->load('items'),
        ]);
    }
}
