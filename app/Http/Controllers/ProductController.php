<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Bom;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('products.view'), 403);

        $filters = $request->only(['sku', 'name', 'category_id', 'type', 'status']);
        $query = Product::query()->with(['category', 'unit', 'variants', 'optionGroups.options']);

        if (filled($filters['sku'] ?? null)) {
            $query->where('sku', 'like', '%'.$filters['sku'].'%');
        }
        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', $filters['category_id']);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        $focusProduct = $request->filled('product')
            ? Product::query()->with(['category', 'unit', 'variants', 'optionGroups.options'])->find($request->integer('product'))
            : null;

        return view('products.index', [
            'products' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('name')->get(),
            'stats' => [
                'total' => Product::query()->count(),
                'sellable' => Product::query()->where('is_sellable', true)->where('is_active', true)->count(),
                'inactive' => Product::query()->where('is_active', false)->count(),
            ],
            'focusPayload' => $focusProduct?->toModalArray(),
        ]);
    }

    public function create(): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('products.manage'), 403);

        return redirect()->route('products.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('products.manage'), 403);
        $product = Product::query()->create($this->payload($request));
        $this->syncVariants($product, $request->input('variants', []));
        $this->syncOptionGroups($product, $request->input('option_groups', []));
        $this->syncRecipeCost($product);

        return redirect()->route('products.index')->with('success', 'Produk ditambahkan.');
    }

    public function edit(Product $product): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('products.manage'), 403);

        return redirect()->route('products.index', ['modal' => 'edit', 'product' => $product->id]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('products.manage'), 403);
        $product->update($this->payload($request, $product));
        $this->syncVariants($product, $request->input('variants', []));
        $this->syncOptionGroups($product, $request->input('option_groups', []));
        $this->syncRecipeCost($product);

        return redirect()->route('products.index')->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('products.manage'), 403);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk dihapus.');
    }

    protected function payload(Request $request, ?Product $product = null): array
    {
        $data = $this->validated($request, $product?->id);
        unset($data['image_file'], $data['variants'], $data['option_groups']);

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('products', 'public');
        }

        return $data;
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $keep = [];

        foreach ($variants as $row) {
            if (! filled($row['name'] ?? null)) {
                continue;
            }

            $payload = [
                'name' => $row['name'],
                'sku' => filled($row['sku'] ?? null) ? $row['sku'] : null,
                'price_adjustment' => $row['price_adjustment'] ?? 0,
                'is_active' => true,
            ];

            $id = (int) ($row['id'] ?? 0);
            $variant = $id > 0
                ? $product->variants()->updateOrCreate(['id' => $id], $payload)
                : $product->variants()->create($payload);

            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep ?: [0])->delete();
    }

    protected function syncOptionGroups(Product $product, array $groups): void
    {
        $keepGroups = [];

        foreach (array_values($groups) as $gIndex => $row) {
            if (! filled($row['name'] ?? null)) {
                continue;
            }

            $isRequired = filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $maxSelect = max(1, (int) ($row['max_select'] ?? 1));
            $minSelect = $isRequired ? max(1, (int) ($row['min_select'] ?? 1)) : max(0, (int) ($row['min_select'] ?? 0));
            if ($minSelect > $maxSelect) {
                $minSelect = $maxSelect;
            }

            $payload = [
                'name' => $row['name'],
                'is_required' => $isRequired,
                'min_select' => $minSelect,
                'max_select' => $maxSelect,
                'sort_order' => $gIndex,
            ];

            $id = (int) ($row['id'] ?? 0);
            $group = $id > 0
                ? $product->optionGroups()->updateOrCreate(['id' => $id], $payload)
                : $product->optionGroups()->create($payload);

            $keepGroups[] = $group->id;
            $keepOptions = [];

            foreach (array_values($row['options'] ?? []) as $oIndex => $optionRow) {
                if (! filled($optionRow['name'] ?? null)) {
                    continue;
                }

                $optionPayload = [
                    'name' => $optionRow['name'],
                    'price_adjustment' => $optionRow['price_adjustment'] ?? 0,
                    'is_active' => filter_var($optionRow['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => $oIndex,
                ];

                $optionId = (int) ($optionRow['id'] ?? 0);
                $option = $optionId > 0
                    ? $group->options()->updateOrCreate(['id' => $optionId], $optionPayload)
                    : $group->options()->create($optionPayload);

                $keepOptions[] = $option->id;
            }

            $group->options()->whereNotIn('id', $keepOptions ?: [0])->delete();
        }

        $product->optionGroups()->whereNotIn('id', $keepGroups ?: [0])->delete();
    }

    protected function syncRecipeCost(Product $product): void
    {
        $bom = $product->fresh()->activeBom();
        if ($bom) {
            $bom->applyToProduct();

            return;
        }

        Bom::syncMenusUsingComponent($product);
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku,'.($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'type' => ['required', 'in:raw,semi_finished,finished,package'],
            'bom_level' => ['nullable', 'integer', 'min:0', 'max:4'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:4096'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'consignment_commission' => ['nullable', 'numeric', 'min:0'],
            'is_sellable' => ['sometimes', 'boolean'],
            'is_stockable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_recommended' => ['sometimes', 'boolean'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'station' => ['nullable', 'in:kitchen,bar,cashier'],
            'prep_minutes' => ['nullable', 'integer', 'min:0'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable'],
            'variants.*.name' => ['nullable', 'string', 'max:80'],
            'variants.*.sku' => ['nullable', 'string', 'max:50'],
            'variants.*.price_adjustment' => ['nullable', 'numeric'],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*.id' => ['nullable'],
            'option_groups.*.name' => ['nullable', 'string', 'max:80'],
            'option_groups.*.is_required' => ['nullable'],
            'option_groups.*.min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'option_groups.*.max_select' => ['nullable', 'integer', 'min:1', 'max:20'],
            'option_groups.*.options' => ['nullable', 'array'],
            'option_groups.*.options.*.id' => ['nullable'],
            'option_groups.*.options.*.name' => ['nullable', 'string', 'max:80'],
            'option_groups.*.options.*.price_adjustment' => ['nullable', 'numeric'],
            'option_groups.*.options.*.is_active' => ['nullable'],
        ], [
            'sku.required' => 'SKU wajib diisi.',
            'sku.unique' => 'SKU sudah dipakai produk lain.',
            'name.required' => 'Nama wajib diisi.',
            'unit_id.required' => 'Unit wajib dipilih.',
            'type.required' => 'Tipe wajib dipilih.',
            'price.required' => 'Harga jual wajib diisi.',
        ]) + [
            'is_sellable' => $request->boolean('is_sellable'),
            'is_stockable' => $request->boolean('is_stockable'),
            'is_active' => $request->boolean('is_active'),
            'is_recommended' => $request->boolean('is_recommended'),
        ];
    }
}
