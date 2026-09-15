<?php

namespace App\Http\Controllers;

use App\Enums\PrinterStation;
use App\Models\Category;
use App\Models\Printer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrinterController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('printers.view'), 403);

        $filters = $request->only(['name', 'station', 'status']);
        $outletId = current_outlet_id();
        $query = Printer::query()
            ->with(['outlet', 'routes.category'])
            ->where('outlet_id', $outletId);

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['station'] ?? null)) {
            $query->where('station', $filters['station']);
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $focusPrinter = $request->filled('printer')
            ? Printer::query()->with(['outlet', 'routes.category'])->where('outlet_id', $outletId)->find($request->integer('printer'))
            : null;

        $baseQuery = fn () => Printer::query()->where('outlet_id', $outletId);

        return view('printers.index', [
            'printers' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(),
            'stations' => PrinterStation::cases(),
            'stats' => [
                'total' => $baseQuery()->count(),
                'active' => $baseQuery()->where('is_active', true)->count(),
                'inactive' => $baseQuery()->where('is_active', false)->count(),
            ],
            'focusPayload' => $focusPrinter?->toModalArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('printers.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'station' => ['required', 'in:cashier,kitchen,bar'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ], [
            'name.required' => 'Nama printer wajib diisi.',
            'station.required' => 'Stasiun wajib dipilih.',
            'ip_address.ip' => 'Alamat IP tidak valid.',
        ]);

        $data['outlet_id'] = current_outlet_id();
        $data['port'] = $data['port'] ?? 9100;
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $printer = Printer::query()->create($data);

        foreach ($categoryIds as $categoryId) {
            $printer->routes()->create(['category_id' => $categoryId, 'station' => $data['station']]);
        }

        return redirect()->route('printers.index')->with('success', 'Printer ditambahkan.');
    }

    public function update(Request $request, Printer $printer): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('printers.manage'), 403);
        abort_unless($printer->outlet_id === current_outlet_id(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'station' => ['required', 'in:cashier,kitchen,bar'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ], [
            'name.required' => 'Nama printer wajib diisi.',
            'station.required' => 'Stasiun wajib dipilih.',
            'ip_address.ip' => 'Alamat IP tidak valid.',
        ]);

        $printer->update(collect($data)->except('category_ids')->all() + [
            'is_active' => $request->boolean('is_active', $printer->is_active),
            'port' => $data['port'] ?? $printer->port ?? 9100,
        ]);

        $printer->routes()->delete();
        foreach ($request->input('category_ids', []) as $categoryId) {
            $printer->routes()->create(['category_id' => $categoryId, 'station' => $data['station']]);
        }

        return redirect()->route('printers.index')->with('success', 'Printer diperbarui.');
    }

    public function destroy(Printer $printer): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('printers.manage'), 403);
        abort_unless($printer->outlet_id === current_outlet_id(), 404);

        $printer->delete();

        return redirect()->route('printers.index')->with('success', 'Printer dihapus.');
    }
}
