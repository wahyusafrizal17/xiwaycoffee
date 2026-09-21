<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MenuDisplayController extends Controller
{
    public const FOCUS_KEY = 'menu_display_focus';

    public const FOCUS_TTL_SECONDS = 60 * 60 * 12;

    public function __invoke(): View
    {
        $drinks = array_values(array_filter([
            $this->column([
                $this->group('Coffee', $this->items('coffee')),
                $this->group('Xiway Main Menu', $this->items('xiway-main')),
            ]),
            $this->column([
                $this->group('Non Coffee', $this->items('non-coffee')),
                $this->group('Fit Tea', $this->items('fit-tea')),
            ]),
        ]));

        $food = array_values(array_filter([
            $this->column([
                $this->group('Makanan', $this->items('makanan')),
            ]),
            $this->column([
                $this->group('Mie', $this->items('mie')),
                $this->group('Snack', $this->items('snack')),
            ]),
        ]));

        $menu = [];
        if ($drinks !== []) {
            $menu[] = [
                'key' => 'drinks',
                'kicker' => 'Xiway Coffee',
                'title' => 'Minuman',
                'columns' => $drinks,
            ];
        }
        if ($food !== []) {
            $menu[] = [
                'key' => 'food',
                'kicker' => 'Xiway Coffee',
                'title' => 'Makanan',
                'columns' => $food,
            ];
        }

        return view('menu.display', [
            'menu' => $menu,
            'focusUrl' => route('menu.display.focus'),
            'initialFocus' => $this->currentFocus(),
        ]);
    }

    public function focus(): JsonResponse
    {
        return response()->json($this->currentFocus());
    }

    public function setFocus(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pos.access'), 403);

        $data = $request->validate([
            'mode' => ['required', 'in:auto,drinks,food'],
        ]);

        $payload = [
            'mode' => $data['mode'],
            'updated_at' => now()->timestamp,
        ];

        Cache::put(self::FOCUS_KEY, $payload, self::FOCUS_TTL_SECONDS);

        return response()->json($payload);
    }

    /**
     * @return array{mode: string, updated_at: int|null}
     */
    protected function currentFocus(): array
    {
        $focus = Cache::get(self::FOCUS_KEY);

        if (! is_array($focus) || ! in_array($focus['mode'] ?? null, ['auto', 'drinks', 'food'], true)) {
            return ['mode' => 'auto', 'updated_at' => null];
        }

        return [
            'mode' => $focus['mode'],
            'updated_at' => isset($focus['updated_at']) ? (int) $focus['updated_at'] : null,
        ];
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
}
