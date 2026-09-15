<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Discount;
use App\Models\Product;

class DiscountService
{
    public function calculate(Discount $discount, float $subtotal, array $items = []): float
    {
        if ($subtotal < (float) $discount->minimum_transaction) {
            return 0;
        }

        $base = $subtotal;

        if (in_array($discount->scope, ['item', 'category'], true) && $items !== []) {
            $eligible = 0;
            $productIds = $discount->items->pluck('product_id')->filter()->all();
            $categoryIds = $discount->items->pluck('category_id')->filter()->all();

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $lineTotal = (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 1);
                $matches = false;

                if ($productIds && in_array($productId, $productIds, true)) {
                    $matches = true;
                }

                if (! $matches && $categoryIds && $productId) {
                    $categoryId = Product::query()->whereKey($productId)->value('category_id');
                    $matches = in_array($categoryId, $categoryIds, true);
                }

                if ($matches) {
                    $eligible += $lineTotal;
                }
            }

            $base = $eligible;
        }

        $amount = $discount->type === DiscountType::Percentage
            ? $base * ((float) $discount->value / 100)
            : (float) $discount->value;

        if ($discount->maximum_discount !== null) {
            $amount = min($amount, (float) $discount->maximum_discount);
        }

        return round(max(0, min($amount, $subtotal)), 2);
    }

    public function activeForOutlet(?int $outletId = null)
    {
        $outletId = $outletId ?? current_outlet_id();

        return Discount::query()
            ->with(['items', 'outlets'])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Discount $discount) => $discount->isCurrentlyActive($outletId))
            ->values();
    }
}
