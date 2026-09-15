<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Order;
use App\Models\Reward;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function earnFromOrder(Order $order): ?CustomerPoint
    {
        if (! $order->customer_id) {
            return null;
        }

        $divisor = max(points_per_amount(), 1);
        $points = (int) floor(((float) $order->grand_total) / $divisor);

        if ($points <= 0) {
            return null;
        }

        return $this->adjust($order->customer, $points, 'earn', 'Pembelian '.$order->order_number, $order);
    }

    public function redeem(Customer $customer, int $points, ?Order $order = null, string $reason = 'Redeem points'): CustomerPoint
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => 'Poin harus lebih dari 0.']);
        }

        if ($customer->points < $points) {
            throw ValidationException::withMessages(['points' => 'Poin pelanggan tidak mencukupi.']);
        }

        return $this->adjust($customer, -$points, 'redeem', $reason, $order);
    }

    public function redeemValue(int $points): float
    {
        $per = max(points_redeem_value(), 1);

        return round(($points / $per) * 10000, 2);
    }

    public function redeemReward(Customer $customer, Reward $reward): CustomerPoint
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages(['reward' => 'Reward tidak aktif.']);
        }

        return $this->redeem($customer, (int) $reward->points_required, null, 'Redeem reward: '.$reward->name);
    }

    public function adjust(Customer $customer, int $points, string $type, string $reason, ?Order $order = null): CustomerPoint
    {
        return DB::transaction(function () use ($customer, $points, $type, $order, $reason) {
            $customer->refresh();
            $balance = max(0, (int) $customer->points + $points);
            $customer->update(['points' => $balance]);
            $customer->refreshMembership();

            return CustomerPoint::query()->create([
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'user_id' => Auth::id(),
                'type' => $type,
                'points' => $points,
                'balance_after' => $balance,
                'reason' => $reason,
            ]);
        });
    }
}
