<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Reward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function discounts(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('marketing.view'), 403);

        $filters = $request->only(['name', 'code', 'type', 'scope', 'status']);
        $today = now()->toDateString();

        $query = Discount::query()->with(['outlets', 'items.product', 'items.category']);

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['code'] ?? null)) {
            $query->where('code', 'like', '%'.$filters['code'].'%');
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (filled($filters['scope'] ?? null)) {
            $query->where('scope', $filters['scope']);
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('marketing.discounts', [
            'discounts' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Discount::query()->count(),
                'active' => Discount::query()
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
                    ->count(),
                'inactive' => Discount::query()->where('is_active', false)->count(),
            ],
            'products' => Product::query()->sellable()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('marketing.manage'), 403);
        [$fields, $relations] = $this->validatedDiscount($request);

        $discount = Discount::query()->create($fields + ['is_active' => true]);
        $this->syncDiscountRelations($discount, $fields['scope'], $relations);

        return redirect()->route('marketing.discounts')->with('success', 'Diskon dibuat.');
    }

    public function updateDiscount(Request $request, Discount $discount): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('marketing.manage'), 403);
        [$fields, $relations] = $this->validatedDiscount($request);
        $discount->update($fields);
        $this->syncDiscountRelations($discount, $fields['scope'], $relations);

        return redirect()->route('marketing.discounts')->with('success', 'Diskon diperbarui.');
    }

    public function destroyDiscount(Request $request, Discount $discount): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('marketing.manage'), 403);
        $discount->delete();

        return redirect()->route('marketing.discounts')->with('success', 'Diskon dihapus.');
    }

    public function toggleDiscount(Request $request, Discount $discount): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('marketing.manage'), 403);
        $discount->update(['is_active' => ! $discount->is_active]);

        return back()->with('success', 'Status diskon diperbarui.');
    }

    public function bundles(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('marketing.view'), 403);

        $filters = $request->only(['name', 'sku', 'status']);
        $today = now()->toDateString();
        $query = Bundle::query()->with(['outlets', 'items.product']);

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['sku'] ?? null)) {
            $query->where('sku', 'like', '%'.$filters['sku'].'%');
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('marketing.bundles', [
            'bundles' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Bundle::query()->count(),
                'active' => Bundle::query()
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
                    ->count(),
                'inactive' => Bundle::query()->where('is_active', false)->count(),
            ],
            'products' => Product::query()->sellable()->orderBy('name')->get(),
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeBundle(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('marketing.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable'],
            'end_time' => ['nullable'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'price.required' => 'Harga wajib diisi.',
            'items.required' => 'Minimal dua produk dalam bundle.',
            'items.min' => 'Minimal dua produk dalam bundle.',
            'items.*.product_id.required' => 'Pilih produk untuk setiap baris.',
            'items.*.quantity.required' => 'Isi jumlah item.',
            'end_date.after_or_equal' => 'Tanggal selesai harus pada atau setelah tanggal mulai.',
        ]);

        $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : null;
        $items = $data['items'];
        $outletIds = $data['outlet_ids'] ?? [];
        unset($data['items'], $data['outlet_ids']);

        $bundle = Bundle::query()->create($data + ['is_active' => true]);
        foreach ($items as $item) {
            $bundle->items()->create($item);
        }
        $bundle->outlets()->sync($outletIds);

        return redirect()->route('marketing.bundles')->with('success', 'Bundle dibuat.');
    }

    public function toggleBundle(Request $request, Bundle $bundle): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('marketing.manage'), 403);
        $bundle->update(['is_active' => ! $bundle->is_active]);

        return back()->with('success', 'Status bundle diperbarui.');
    }

    public function rewards(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('loyalty.view'), 403);

        $filters = $request->only(['name', 'status']);
        $query = Reward::query();

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('loyalty.rewards', [
            'rewards' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Reward::query()->count(),
                'active' => Reward::query()->where('is_active', true)->count(),
                'inactive' => Reward::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function storeReward(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('loyalty.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'points_required' => ['required', 'integer', 'min:1'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'points_required.required' => 'Poin dibutuhkan wajib diisi.',
            'points_required.min' => 'Poin dibutuhkan minimal 1.',
        ]);

        $data['description'] = filled($data['description'] ?? null) ? $data['description'] : null;
        $data['value'] = filled($data['value'] ?? null) ? $data['value'] : null;

        Reward::query()->create($data + ['is_active' => true]);

        return redirect()->route('loyalty.rewards')->with('success', 'Reward ditambahkan.');
    }

    public function toggleReward(Request $request, Reward $reward): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('loyalty.manage'), 403);
        $reward->update(['is_active' => ! $reward->is_active]);

        return back()->with('success', 'Status reward diperbarui.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{outlet_ids: array<int, int>, product_ids: array<int, int>, category_ids: array<int, int>}}
     */
    private function validatedDiscount(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'type' => ['required', 'in:percentage,nominal'],
            'scope' => ['required', 'in:order,item,category'],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_transaction' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable'],
            'end_time' => ['nullable'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'value.required' => 'Nilai wajib diisi.',
            'type.required' => 'Tipe wajib dipilih.',
            'scope.required' => 'Cakupan wajib dipilih.',
            'end_date.after_or_equal' => 'Tanggal selesai harus pada atau setelah tanggal mulai.',
        ]);

        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : null;

        return [
            collect($data)->except(['outlet_ids', 'product_ids', 'category_ids'])->all(),
            [
                'outlet_ids' => $data['outlet_ids'] ?? [],
                'product_ids' => $data['product_ids'] ?? [],
                'category_ids' => $data['category_ids'] ?? [],
            ],
        ];
    }

    /**
     * @param  array{outlet_ids: array<int, int>, product_ids: array<int, int>, category_ids: array<int, int>}  $relations
     */
    private function syncDiscountRelations(Discount $discount, string $scope, array $relations): void
    {
        $discount->outlets()->sync($relations['outlet_ids']);
        $discount->items()->delete();

        if ($scope === 'item') {
            foreach ($relations['product_ids'] as $id) {
                $discount->items()->create(['product_id' => $id]);
            }
        }

        if ($scope === 'category') {
            foreach ($relations['category_ids'] as $id) {
                $discount->items()->create(['category_id' => $id]);
            }
        }
    }
}
