<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
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

        return view('site.menu', [
            'groups' => $products,
            'site' => config('site'),
        ]);
    }
}
