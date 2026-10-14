<?php

use App\Models\Outlet;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function money(float|int|string|null $amount): string
{
    return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
}

function money_decimal(float|int|string|null $amount): string
{
    return number_format((float) ($amount ?? 0), 2, ',', '.');
}

function qty(float|int|string|null $value): string
{
    return number_format((float) ($value ?? 0), 0, ',', '.');
}

function current_outlet_id(): ?int
{
    $id = session('current_outlet_id');

    return $id ? (int) $id : null;
}

function current_outlet(): ?Outlet
{
    $id = current_outlet_id();

    if (! $id) {
        return null;
    }

    return Outlet::query()->find($id);
}

function setting(string $key, mixed $default = null, ?int $outletId = null): mixed
{
    $outletId = $outletId ?? current_outlet_id();
    $cacheKey = "setting.{$outletId}.{$key}";

    return Cache::remember($cacheKey, 120, function () use ($key, $default, $outletId) {
        $row = Setting::query()
            ->where('key', $key)
            ->where(function ($q) use ($outletId) {
                $q->whereNull('outlet_id');
                if ($outletId) {
                    $q->orWhere('outlet_id', $outletId);
                }
            })
            ->orderByRaw('outlet_id is null')
            ->first();

        return $row?->value ?? $default;
    });
}

function tax_rate(): float
{
    return (float) setting('tax_rate', 10);
}

/**
 * Saved plan, or the config list until someone edits it.
 *
 * @return list<array<string, mixed>>
 */
function bop_plan_source(): array
{
    $stored = setting('bop_plan');
    if (is_string($stored) && $stored !== '') {
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }
    }

    return config('pos.bop.items', []);
}

/**
 * @return list<array{name: string, category: string, amount: float, period: string, monthly: float}>
 */
function bop_items(): array
{
    return array_map(function (array $item) {
        $amount = (float) $item['amount'];
        $monthly = ($item['period'] ?? 'month') === 'year' ? round($amount / 12, 2) : $amount;

        return [
            'name' => (string) $item['name'],
            'category' => (string) ($item['category'] ?? ''),
            'amount' => $amount,
            'period' => (string) ($item['period'] ?? 'month'),
            'monthly' => $monthly,
        ];
    }, bop_plan_source());
}

function monthly_bop(): float
{
    return round(array_sum(array_column(bop_items(), 'monthly')), 2);
}

/** @return list<string> */
function drink_category_names(): array
{
    return config('pos.bop.drink_categories', ['Coffee', 'Non Coffee', 'Fit Tea', 'Xiway Main']);
}

function order_item_discount_share_sql(): string
{
    $scope = '(SELECT d.scope FROM discounts d WHERE d.id = orders.discount_id)';
    $categoryMatch = 'EXISTS (
        SELECT 1 FROM discount_items di
        JOIN products p ON p.id = order_items.product_id AND p.category_id = di.category_id
        WHERE di.discount_id = orders.discount_id
    )';
    $categoryBase = 'NULLIF((
        SELECT SUM(oi.total) FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN discount_items di ON di.discount_id = orders.discount_id AND di.category_id = p.category_id
        WHERE oi.order_id = orders.id
    ), 0)';
    $itemMatch = 'EXISTS (
        SELECT 1 FROM discount_items di
        WHERE di.discount_id = orders.discount_id AND di.product_id = order_items.product_id
    )';
    $itemBase = 'NULLIF((
        SELECT SUM(oi.total) FROM order_items oi
        JOIN discount_items di ON di.discount_id = orders.discount_id AND di.product_id = oi.product_id
        WHERE oi.order_id = orders.id
    ), 0)';

    return "CASE
        WHEN COALESCE(orders.discount_amount, 0) <= 0 OR COALESCE(orders.subtotal, 0) <= 0 OR COALESCE(order_items.total, 0) <= 0 THEN 0
        WHEN {$scope} = 'category' AND {$categoryMatch} THEN orders.discount_amount * order_items.total / {$categoryBase}
        WHEN {$scope} = 'category' THEN 0
        WHEN {$scope} = 'item' AND {$itemMatch} THEN orders.discount_amount * order_items.total / {$itemBase}
        WHEN {$scope} = 'item' THEN 0
        ELSE orders.discount_amount * order_items.total / orders.subtotal
    END";
}

function order_item_net_total_sql(): string
{
    $share = 'COALESCE(('.order_item_discount_share_sql().'), 0)';

    return "CASE WHEN {$share} >= order_items.total THEN 0 ELSE order_items.total - {$share} END";
}

function order_item_drink_amount_sql(): string
{
    $names = collect(drink_category_names())
        ->map(fn (string $name) => "'".str_replace("'", "''", $name)."'")
        ->implode(', ');
    $share = 'bundles.drink_share';
    $net = order_item_net_total_sql();

    return "CASE
        WHEN {$share} IS NOT NULL AND order_items.unit_price > 0 THEN ({$net}) * {$share} / order_items.unit_price
        WHEN categories.name IN ({$names}) THEN ({$net})
        ELSE 0 END";
}

function order_item_drink_cups_sql(): string
{
    $names = collect(drink_category_names())
        ->map(fn (string $name) => "'".str_replace("'", "''", $name)."'")
        ->implode(', ');

    return "CASE
        WHEN LOWER(order_items.name) LIKE '%air mineral%' THEN 0
        WHEN bundles.drink_share > 0 THEN order_items.quantity
        WHEN bundles.drink_share IS NULL AND categories.name IN ({$names}) THEN order_items.quantity
        ELSE 0 END";
}

function order_item_food_amount_sql(): string
{
    $share = 'bundles.drink_share';
    $net = order_item_net_total_sql();

    return "CASE
        WHEN {$share} IS NOT NULL AND order_items.unit_price > 0 THEN ({$net}) - (({$net}) * {$share} / order_items.unit_price)
        WHEN order_items.consignment_commission > 0 THEN ({$net})
        ELSE 0 END";
}

function points_per_amount(): int
{
    return (int) setting('points_earn_per_amount', 10000);
}

function points_redeem_value(): int
{
    return (int) setting('points_redeem_value', 100);
}

function terbilang(int $number): string
{
    $number = abs($number);
    if ($number === 0) {
        return 'nol';
    }

    $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    $join = function (string $head, int $rest): string {
        return $rest === 0 ? $head : trim($head.' '.terbilang($rest));
    };

    if ($number < 12) {
        return $words[$number];
    }
    if ($number < 20) {
        return terbilang($number - 10).' belas';
    }
    if ($number < 100) {
        return $join(terbilang(intdiv($number, 10)).' puluh', $number % 10);
    }
    if ($number < 200) {
        return $join('seratus', $number - 100);
    }
    if ($number < 1000) {
        return $join(terbilang(intdiv($number, 100)).' ratus', $number % 100);
    }
    if ($number < 2000) {
        return $join('seribu', $number - 1000);
    }
    if ($number < 1000000) {
        return $join(terbilang(intdiv($number, 1000)).' ribu', $number % 1000);
    }
    if ($number < 1000000000) {
        return $join(terbilang(intdiv($number, 1000000)).' juta', $number % 1000000);
    }

    return (string) $number;
}

function public_menu_description(?string $description): ?string
{
    if ($description === null || trim($description) === '') {
        return null;
    }

    $lower = strtolower($description);
    if (str_contains($lower, 'menu mitra') || str_contains($lower, 'komisi cafe')) {
        return null;
    }

    return $description;
}

function evidence_url(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }
    if (is_file(public_path($path))) {
        return asset($path);
    }

    try {
        return Storage::disk('biznet')->temporaryUrl($path, now()->addHours(12));
    } catch (\Throwable) {
        return $path;
    }
}
