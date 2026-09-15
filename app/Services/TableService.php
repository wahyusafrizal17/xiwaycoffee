<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\TableReservation;
use App\Models\TableSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TableService
{
    public function occupy(int $tableId, Order $order): DiningTable
    {
        $table = DiningTable::query()->findOrFail($tableId);

        $table->update(['status' => TableStatus::Occupied]);

        TableSession::query()->create([
            'table_id' => $table->id,
            'order_id' => $order->id,
            'opened_by' => Auth::id(),
            'guest_count' => $order->guest_count,
            'opened_at' => now(),
        ]);

        return $table->fresh();
    }

    public function release(int $tableId): DiningTable
    {
        $table = DiningTable::query()->findOrFail($tableId);

        $table->sessions()->whereNull('closed_at')->update(['closed_at' => now()]);
        $table->update(['status' => TableStatus::Available]);

        return $table->fresh();
    }

    public function reserve(array $data): TableReservation
    {
        return DB::transaction(function () use ($data) {
            $table = DiningTable::query()->findOrFail($data['table_id']);

            if ($table->status === TableStatus::Occupied) {
                throw ValidationException::withMessages(['table_id' => 'Meja sedang terisi.']);
            }

            $reservation = TableReservation::query()->create([
                'outlet_id' => $data['outlet_id'],
                'table_id' => $table->id,
                'customer_id' => $data['customer_id'] ?? null,
                'guest_name' => $data['guest_name'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_count' => $data['guest_count'] ?? 2,
                'reserved_at' => $data['reserved_at'],
                'status' => 'reserved',
                'notes' => $data['notes'] ?? null,
            ]);

            $table->update(['status' => TableStatus::Reserved]);

            return $reservation;
        });
    }

    public function transfer(int $fromId, int $toId): void
    {
        DB::transaction(function () use ($fromId, $toId) {
            $from = DiningTable::query()->findOrFail($fromId);
            $to = DiningTable::query()->findOrFail($toId);

            if ($to->status !== TableStatus::Available) {
                throw ValidationException::withMessages(['to' => 'Meja tujuan tidak tersedia.']);
            }

            $order = Order::query()
                ->where('table_id', $from->id)
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->latest('id')
                ->first();

            if ($order) {
                $order->update(['table_id' => $to->id]);
            }

            $from->sessions()->whereNull('closed_at')->update(['table_id' => $to->id]);
            $to->update(['status' => TableStatus::Occupied]);
            $from->update(['status' => TableStatus::Available]);
        });
    }

    public function merge(int $sourceId, int $targetId): void
    {
        DB::transaction(function () use ($sourceId, $targetId) {
            $sourceOrder = Order::query()
                ->where('table_id', $sourceId)
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->latest('id')
                ->first();

            $targetOrder = Order::query()
                ->where('table_id', $targetId)
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->latest('id')
                ->first();

            if (! $sourceOrder || ! $targetOrder) {
                throw ValidationException::withMessages(['tables' => 'Kedua meja harus punya order aktif. Pilih dua meja Occupied.']);
            }

            $sourceCode = DiningTable::query()->whereKey($sourceId)->value('code');

            foreach ($sourceOrder->items as $item) {
                $item->update(['order_id' => $targetOrder->id]);
            }

            app(OrderService::class)->recalculate($targetOrder->fresh(['items', 'discount']));
            $targetOrder->update([
                'notes' => trim(implode(' · ', array_filter([
                    $targetOrder->notes,
                    $sourceCode ? 'Gabung dari '.$sourceCode : null,
                ]))),
            ]);
            $sourceOrder->update([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => 'Digabung ke '.$targetOrder->order_number,
                'cancelled_at' => now(),
            ]);

            $this->release($sourceId);
        });
    }

    public function split(int $sourceId, int $targetId, array $itemIds): Order
    {
        return DB::transaction(function () use ($sourceId, $targetId, $itemIds) {
            $itemIds = array_values(array_filter(array_map('intval', $itemIds)));

            if ($itemIds === []) {
                throw ValidationException::withMessages(['items' => 'Pilih minimal satu item untuk dipisah.']);
            }

            $target = DiningTable::query()->findOrFail($targetId);

            if ($target->status !== TableStatus::Available) {
                throw ValidationException::withMessages(['to' => 'Meja tujuan harus tersedia.']);
            }

            $sourceOrder = Order::query()
                ->with('items')
                ->where('table_id', $sourceId)
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->latest('id')
                ->first();

            if (! $sourceOrder) {
                throw ValidationException::withMessages(['from' => 'Meja sumber tidak memiliki order aktif.']);
            }

            $moving = $sourceOrder->items->whereIn('id', $itemIds);

            if ($moving->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Item yang dipilih tidak ada di order sumber.']);
            }

            if ($moving->count() >= $sourceOrder->items->count()) {
                throw ValidationException::withMessages(['items' => 'Sisakan minimal satu item di meja sumber.']);
            }

            $newOrder = app(OrderService::class)->createDraft([
                'outlet_id' => $sourceOrder->outlet_id,
                'customer_id' => $sourceOrder->customer_id,
                'table_id' => $target->id,
                'channel' => $sourceOrder->channel?->value ?? 'pos',
                'order_type' => $sourceOrder->order_type?->value ?? 'dine_in',
                'guest_count' => $sourceOrder->guest_count,
                'notes' => 'Split dari '.$sourceOrder->order_number,
            ]);

            foreach ($moving as $item) {
                $item->update(['order_id' => $newOrder->id]);
            }

            $orders = app(OrderService::class);
            $orders->recalculate($sourceOrder->fresh(['items', 'discount']));
            $orders->recalculate($newOrder->fresh(['items', 'discount']));

            if ($sourceOrder->status !== OrderStatus::Draft) {
                $orders->transition($newOrder->fresh(), $sourceOrder->status, 'Split dari '.$sourceOrder->order_number);
            }

            return $newOrder->fresh(['items', 'table']);
        });
    }

    public function move(int $tableId, int $x, int $y): DiningTable
    {
        $table = DiningTable::query()->findOrFail($tableId);
        $table->update([
            'pos_x' => max(0, $x),
            'pos_y' => max(0, $y),
        ]);

        return $table;
    }
}
