<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class MenuDisplayController extends Controller
{
    public function __invoke(): View
    {
        // Clean scan path: food | coffee | the rest
        $columns = array_values(array_filter([
            $this->column([
                $this->group('Makanan', $this->items('makanan')),
                $this->group('Snack', $this->items('snack')),
            ]),
            $this->column([
                $this->group('Coffee', $this->items('coffee')),
                $this->group('Xiway Main', $this->items('xiway-main')),
                $this->group('Paket Bundle', $this->bundles()),
            ]),
            $this->column([
                $this->group('Non Coffee', $this->items('non-coffee')),
                $this->group('Fit Tea', $this->items('fit-tea')),
                $this->group('Mie', $this->items('mie')),
            ]),
        ]));

        return view('menu.display', [
            'menu' => $columns === [] ? [] : [[
                'kicker' => 'Xiway Coffee',
                'title' => 'Menu',
                'columns' => $columns,
            ]],
        ]);
    }

    /**
     * @param  list<array{name: string, items: list<array{name: string, price: int, star: bool}>}>  $groups
     * @return list<array{name: string, items: list<array{name: string, price: int, star: bool}>}>|null
     */
    protected function column(array $groups): ?array
    {
        $groups = array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));

        return $groups === [] ? null : $groups;
    }

    /**
     * @param  list<array{name: string, price: int, star: bool}>  $items
     * @return array{name: string, items: list<array{name: string, price: int, star: bool}>}
     */
    protected function group(string $name, array $items): array
    {
        return compact('name', 'items');
    }

    /**
     * @return list<array{name: string, price: int, star: bool}>
     */
    protected function items(string $slug): array
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();
        if (! $category) {
            return [];
        }

        return Product::query()
            ->sellable()
            ->where('category_id', $category->id)
            ->orderByDesc('is_recommended')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'name' => $product->name,
                'price' => (int) round((float) $product->price / 1000),
                'star' => (bool) $product->is_recommended,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, price: int, star: bool}>
     */
    protected function bundles(): array
    {
        return Bundle::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Bundle $bundle) => $bundle->isCurrentlyActive())
            ->map(fn (Bundle $bundle) => [
                'name' => $bundle->name,
                'price' => (int) round((float) $bundle->price / 1000),
                'star' => false,
            ])
            ->values()
            ->all();
    }
}
