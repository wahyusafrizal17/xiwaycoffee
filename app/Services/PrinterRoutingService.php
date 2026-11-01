<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PrinterStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrinterRoute;
use Illuminate\Support\Collection;

class PrinterRoutingService
{
    /**
     * Resolve print jobs for an order. Physical printer integration is intentionally
     * abstracted so a driver can be plugged in later.
     *
     * @return array<int, array{printer:Printer, items:array}>
     */
    public function route(Order $order): array
    {
        $order->loadMissing('items.product.category', 'outlet');

        $printers = Printer::query()
            ->with('routes')
            ->where('outlet_id', $order->outlet_id)
            ->where('is_active', true)
            ->get();

        $jobs = [];

        foreach ($printers as $printer) {
            $items = $order->items->filter(fn ($item) => ! $item->printed_at)->filter(function ($item) use ($printer) {
                $station = $item->station ?? $item->product?->station ?? $item->product?->category?->station;
                $categoryId = $item->product?->category_id;

                if ($printer->routes->isEmpty()) {
                    return $station && $station === $printer->station->value;
                }

                return $printer->routes->contains(function (PrinterRoute $route) use ($station, $categoryId) {
                    if ($route->category_id && $route->category_id === $categoryId) {
                        return true;
                    }

                    return $route->station && $route->station === $station;
                });
            });

            if ($items->isNotEmpty()) {
                $jobs[] = [
                    'printer' => $printer,
                    'items' => $items->values()->all(),
                    'payload' => $this->payload($order, $printer, $items->all()),
                ];
            }
        }

        return $jobs;
    }

    public function payload(Order $order, Printer $printer, array $items): array
    {
        return [
            'type' => 'ticket',
            'station' => $printer->station->value,
            'printer' => [
                'name' => $printer->name,
                'ip' => $printer->ip_address,
                'port' => $printer->port,
            ],
            'order_number' => $order->order_number,
            'table' => $order->table?->code,
            'items' => collect($items)->map(fn ($item) => [
                'name' => $item->name,
                'qty' => $item->quantity,
                'notes' => $item->notes,
            ])->all(),
        ];
    }

    public function stationItems(Order $order, string $station): Collection
    {
        $order->loadMissing('items.product.category');

        return $order->items->filter(function ($item) use ($station) {
            $itemStation = $item->station ?? $item->product?->station ?? $item->product?->category?->station;

            return $itemStation === $station;
        })->values();
    }

    /** Item yang sudah masuk dapur tapi tiketnya belum tercetak atau terkirim. */
    public function pendingItems(Order $order, string $station): Collection
    {
        return $this->stationItems($order, $station)
            ->filter(fn ($item) => $item->sent_at && ! $item->printed_at)
            ->values();
    }

    public function printedColumn(string $station): ?string
    {
        return match ($station) {
            PrinterStation::Kitchen->value => 'kitchen_printed_at',
            PrinterStation::Bar->value => 'bar_printed_at',
            default => null,
        };
    }

    /**
     * @param  list<int>|null  $itemIds  Kosong berarti semua item yang menunggu.
     */
    public function markPrinted(Order $order, string $station, ?array $itemIds = null): void
    {
        $column = $this->printedColumn($station);
        if (! $column) {
            return;
        }

        $ids = $this->pendingItems($order->fresh(), $station)->pluck('id')
            ->when($itemIds !== null, fn ($pending) => $pending->intersect($itemIds));
        OrderItem::query()->whereKey($ids)->update(['printed_at' => now()]);

        $order->forceFill([$column => now()])->save();
    }

    public function dispatchNetworkTickets(Order $order): void
    {
        $order->loadMissing(['items.product.category', 'table', 'outlet']);
        $escpos = app(EscPosPrinter::class);

        foreach ([PrinterStation::Kitchen->value, PrinterStation::Bar->value] as $station) {
            $items = $this->pendingItems($order, $station);
            if ($items->isEmpty()) {
                continue;
            }

            $printer = Printer::query()
                ->where('outlet_id', $order->outlet_id)
                ->where('station', $station)
                ->where('is_active', true)
                ->whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->first();

            if (! $printer) {
                continue;
            }

            $bytes = $escpos->ticket($order, $station, $items);
            if ($escpos->send($printer->ip_address, (int) ($printer->port ?: 9100), $bytes)) {
                $this->markPrinted($order, $station, $items->pluck('id')->all());
            }
        }
    }

    /**
     * @return list<array{order_id:int,order_number:string,station:string,printer:?array,escpos:string,ack_url:string}>
     */
    public function pendingJobs(?int $outletId, string $station): array
    {
        $column = $this->printedColumn($station);
        if (! $column || ! $outletId) {
            return [];
        }

        $escpos = app(EscPosPrinter::class);
        $jobs = [];

        $orders = Order::query()
            ->with(['items.product.category', 'table', 'outlet'])
            ->where('outlet_id', $outletId)
            ->whereHas('items', fn ($items) => $items->whereNotNull('sent_at')->whereNull('printed_at'))
            ->whereNotIn('status', [
                OrderStatus::Draft->value,
                OrderStatus::Completed->value,
                OrderStatus::Cancelled->value,
            ])
            ->latest()
            ->limit(20)
            ->get();

        foreach ($orders as $order) {
            $items = $this->pendingItems($order, $station);
            if ($items->isEmpty()) {
                continue;
            }

            $printer = Printer::query()
                ->where('outlet_id', $order->outlet_id)
                ->where('station', $station)
                ->where('is_active', true)
                ->first();

            $jobs[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'station' => $station,
                'printer' => $printer ? [
                    'name' => $printer->name,
                    'ip' => $printer->ip_address,
                    'port' => (int) ($printer->port ?: 9100),
                ] : null,
                'escpos' => base64_encode($escpos->ticket($order, $station, $items)),
                'ack_url' => route('print-jobs.ack', ['order' => $order, 'station' => $station, 'items' => $items->pluck('id')->implode(',')]),
            ];
        }

        return $jobs;
    }
}
