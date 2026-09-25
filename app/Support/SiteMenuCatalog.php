<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class SiteMenuCatalog
{
    /**
     * @return Collection<string, Collection<int, object{name: string, price: float, description: ?string, star: bool}>>
     */
    public static function groups(): Collection
    {
        return Product::query()
            ->sellable()
            ->with('category')
            ->whereHas('category', fn ($q) => $q->where('name', '!=', 'Ingredients')->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Product $product) => $product->category?->name ?: 'Lainnya')
            ->sortKeysUsing(function (string $a, string $b) {
                $order = ['Coffee' => 1, 'Xiway Main' => 2, 'Non Coffee' => 3, 'Fit Tea' => 4, 'Makanan' => 5, 'Mie' => 6, 'Snack' => 7];

                return ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b);
            })
            ->map(fn (Collection $items) => $items->map(fn (Product $product) => (object) [
                'name' => $product->name,
                'price' => (float) $product->price,
                'description' => public_menu_description($product->description),
                'star' => (bool) $product->is_recommended,
            ]));
    }
}
