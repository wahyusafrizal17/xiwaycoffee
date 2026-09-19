<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Investor;
use App\Models\OperatingExpense;
use App\Models\Order;
use App\Models\OrderItem;

class ProfitShareService
{
    /**
     * @return list<array{name: string, capital: float}>
     */
    public function partners(): array
    {
        $fromDb = Investor::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['name', 'capital']);

        if ($fromDb->isNotEmpty()) {
            return $fromDb
                ->map(fn (Investor $investor) => [
                    'name' => $investor->name,
                    'capital' => (float) $investor->capital,
                ])
                ->all();
        }

        return array_map(
            fn (array $partner) => [
                'name' => $partner['name'],
                'capital' => (float) $partner['capital'],
            ],
            config('pos.partners', []),
        );
    }

    /**
     * @param  list<array{name: string, capital: int|float}>  $partners
     * @return list<array{name: string, capital: int|float, percent: float, amount: float}>
     */
    public function split(float $remainder, array $partners): array
    {
        $total = array_sum(array_column($partners, 'capital'));
        $shares = [];
        $allocated = 0.0;
        $last = count($partners) - 1;

        foreach ($partners as $i => $partner) {
            $amount = $i === $last
                ? round($remainder - $allocated, 2)
                : round($remainder * $partner['capital'] / $total, 2);
            $allocated += $amount;
            $shares[] = [
                'name' => $partner['name'],
                'capital' => $partner['capital'],
                'percent' => $total > 0 ? $partner['capital'] / $total * 100 : 0,
                'amount' => $amount,
            ];
        }

        return $shares;
    }

    public function summarize(array $filters): array
    {
        $gross = (float) Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->sum('grand_total');

        $setoran = $this->foodSetoran($filters);
        $sales = round($gross - $setoran, 2);

        $expenses = OperatingExpense::query()
            ->with('user')
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('spent_on', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('spent_on', '<=', $filters['to']))
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();

        $bop = (float) $expenses->sum('amount');
        $remainder = round($sales - $bop, 2);

        return [
            'sales' => $sales,
            'gross' => $gross,
            'food_setoran' => $setoran,
            'bop' => $bop,
            'remainder' => $remainder,
            'shares' => $this->split($remainder, $this->partners()),
            'expenses' => $expenses,
        ];
    }

    public function foodSetoran(array $filters): float
    {
        $items = OrderItem::query()
            ->where('consignment_commission', '>', 0)
            ->whereHas('order', function ($order) use ($filters) {
                $order->where('payment_status', PaymentStatus::Paid->value)
                    ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('outlet_id', $filters['outlet_id']))
                    ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
                    ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));
            })
            ->get(['total', 'quantity', 'consignment_commission']);

        return round($items->sum(fn (OrderItem $item) => (float) $item->total - ((float) $item->consignment_commission * (float) $item->quantity)), 2);
    }
}
