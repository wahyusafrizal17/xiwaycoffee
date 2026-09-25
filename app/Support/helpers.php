<?php

use App\Models\Outlet;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

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
 * @return list<array{name: string, category: string, amount: float, period: string, monthly: float}>
 */
function bop_items(): array
{
    return array_map(function (array $item) {
        $amount = (float) $item['amount'];
        $monthly = ($item['period'] ?? 'month') === 'year' ? round($amount / 12, 2) : $amount;

        return [
            'name' => (string) $item['name'],
            'category' => (string) $item['category'],
            'amount' => $amount,
            'period' => (string) ($item['period'] ?? 'month'),
            'monthly' => $monthly,
        ];
    }, config('pos.bop.items', []));
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

function points_per_amount(): int
{
    return (int) setting('points_earn_per_amount', 10000);
}

function points_redeem_value(): int
{
    return (int) setting('points_redeem_value', 100);
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
