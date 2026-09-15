<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PrinterStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Services\OrderService;
use App\Services\PrinterRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckerController extends Controller
{
    public function kitchen(Request $request): View
    {
        return $this->board($request, PrinterStation::Kitchen->value, 'Kitchen');
    }

    public function bar(Request $request): View
    {
        return $this->board($request, PrinterStation::Bar->value, 'Bar');
    }

    public function pendingJobs(Request $request, PrinterRoutingService $printers): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders.check'), 403);

        $station = $this->requestedStation($request);
        abort_unless($station, 403);

        $outletId = current_outlet_id();
        $printer = Printer::query()
            ->where('outlet_id', $outletId)
            ->where('station', $station)
            ->where('is_active', true)
            ->value('name');

        return response()->json([
            'station' => $station,
            'printer' => $printer ?: '',
            'jobs' => $printers->pendingJobs($outletId, $station),
        ]);
    }

    public function ackJob(Request $request, Order $order, PrinterRoutingService $printers): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders.check'), 403);

        $station = $this->requestedStation($request);
        abort_unless($station, 403);
        abort_unless((int) $order->outlet_id === (int) current_outlet_id(), 403);

        if ($printers->stationItems($order, $station)->isEmpty()) {
            abort(403);
        }

        $printers->markPrinted($order, $station);

        return response()->json(['ok' => true]);
    }

    public function updateItem(Request $request, OrderItem $item, OrderService $orders): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('orders.check'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:new,preparing,ready,served'],
        ]);

        $station = $this->itemStation($item);

        if ($station === PrinterStation::Kitchen->value && $data['status'] === 'served') {
            abort_unless($item->status === 'ready', 403);
        }

        $orders->updateItemStatus($item, $data['status']);

        return back()->with('success', 'Item dikonfirmasi.');
    }

    protected function board(Request $request, string $station, string $title): View
    {
        abort_unless($request->user()->hasPermission('orders.check'), 403);

        $outletId = current_outlet_id();

        $grouped = OrderItem::query()
            ->with(['order.table', 'order.customer', 'product', 'variant', 'confirmedBy'])
            ->whereHas('order', function ($query) use ($outletId) {
                $query->where('outlet_id', $outletId)
                    ->whereNotIn('status', [
                        OrderStatus::Draft->value,
                        OrderStatus::Held->value,
                        OrderStatus::Completed->value,
                        OrderStatus::Cancelled->value,
                    ]);
            })
            ->where(function ($query) use ($station) {
                $stations = $station === PrinterStation::Bar->value
                    ? [PrinterStation::Bar->value, PrinterStation::Kitchen->value]
                    : [$station];

                $query->whereIn('station', $stations)
                    ->orWhereHas('product', fn ($product) => $product->whereIn('station', $stations));
            })
            ->whereIn('status', ['new', 'preparing', 'ready'])
            ->orderBy('id')
            ->get()
            ->groupBy('order_id')
            ->sortBy(fn ($items) => $items->first()?->order?->created_at);

        $columns = [
            'new' => collect(),
            'preparing' => collect(),
            'ready' => collect(),
        ];

        foreach ($grouped as $orderId => $items) {
            $bucket = $items->contains(fn ($item) => $item->status === 'new')
                ? 'new'
                : ($items->contains(fn ($item) => $item->status === 'preparing') ? 'preparing' : 'ready');

            $columns[$bucket]->put($orderId, $items);
        }

        return view('checkers.board', [
            'title' => $title,
            'station' => $station,
            'columns' => $columns,
            'counts' => [
                'new' => $columns['new']->count(),
                'preparing' => $columns['preparing']->count(),
                'ready' => $columns['ready']->count(),
                'items' => $grouped->flatten()->count(),
            ],
        ]);
    }

    protected function requestedStation(Request $request): ?string
    {
        $station = $request->query('station', $request->input('station'));

        return in_array($station, [PrinterStation::Kitchen->value, PrinterStation::Bar->value], true)
            ? $station
            : null;
    }

    protected function itemStation(OrderItem $item): string
    {
        return (string) ($item->station
            ?? $item->product?->station
            ?? $item->product?->category?->station
            ?? '');
    }
}
