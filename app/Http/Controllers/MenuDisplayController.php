<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class MenuDisplayController extends Controller
{
    public function __invoke(): View
    {
        return view('menu.display', [
            'menu' => array_values(array_filter([
                $this->page('Menu', 'Minuman', '1fr 1fr', [
                    $this->group('Coffee', 'Espresso & susu', $this->items('coffee')),
                    $this->group('Non Coffee', 'Tanpa kopi', $this->items('non-coffee')),
                    $this->group('Fit Tea', 'Teh segar', $this->items('fit-tea')),
                    $this->group('Xiway Main', 'Signature', $this->items('xiway-main')),
                ]),
                $this->page('Menu', 'Makanan', '1.25fr 1fr .85fr', [
                    $this->group('Makanan', 'Dimasak setelah dipesan', $this->items('makanan')),
                    $this->group('Mie', 'Level pedas bisa diatur', $this->items('mie')),
                    $this->group('Snack', 'Teman ngopi', $this->items('snack')),
                ]),
            ])),
        ]);
    }

    /**
     * @param  list<array{name: string, note: ?string, span: ?int, cols: int, items: list<array{name: string, price: int, star: bool}>}>  $groups
     * @return array{kicker: string, title: string, layout: string, groups: list<array{name: string, note: ?string, span: ?int, cols: int, items: list<array{name: string, price: int, star: bool}>}>}|null
     */
    protected function page(string $kicker, string $title, string $layout, array $groups): ?array
    {
        $groups = array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
        if ($groups === []) {
            return null;
        }

        return compact('kicker', 'title', 'layout', 'groups');
    }

    /**
     * @param  list<array{name: string, price: int, star: bool}>  $items
     * @return array{name: string, note: ?string, span: ?int, cols: int, items: list<array{name: string, price: int, star: bool}>}
     */
    protected function group(string $name, ?string $note, array $items, ?int $span = null, int $cols = 1): array
    {
        return compact('name', 'note', 'span', 'cols', 'items');
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
}
