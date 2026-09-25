<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Outlet;
use App\Services\DashboardService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        abort_unless($request->user()->hasPermission('orders.view'), 403);

        $period = $request->string('period')->toString();
        if ($period === 'today') {
            $period = 'day';
        }
        if (! in_array($period, ['day', 'month', 'year', 'range'], true)) {
            $period = 'day';
        }

        $range = $dashboard->resolveRange([
            'period' => $period,
            'date' => $request->string('date')->toString() ?: null,
            'month' => $request->string('month')->toString() ?: null,
            'year' => $request->string('year')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ]);

        $pending = [OrderStatus::Draft->value, OrderStatus::Held->value];

        $orders = Order::query()
            ->with(['outlet', 'customer', 'table', 'user'])
            ->when(
                filled($request->status),
                fn ($q) => $q->where('status', $request->status),
                fn ($q) => $q->whereNotIn('status', $pending),
            )
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to'])
            ->when($request->order_number, fn ($q, $s) => $q->where('order_number', 'like', "%{$s}%"))
            ->when($request->outlet_id, fn ($q, $id) => $q->where('outlet_id', $id))
            ->when($request->customer, fn ($q, $s) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")))
            ->when($request->table, fn ($q, $s) => $q->whereHas('table', fn ($t) => $t->where('code', 'like', "%{$s}%")))
            ->when($request->order_type, fn ($q, $t) => $q->where('order_type', $t))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->cashier, fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counted = Order::query()
            ->whereNotIn('status', $pending)
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to']);

        return view('orders.index', [
            'orders' => $orders,
            'outlets' => Outlet::query()->orderBy('name')->get(),
            'period' => $range['period'],
            'range' => $range,
            'filters' => $request->only([
                'order_number', 'outlet_id', 'customer', 'table',
                'order_type', 'status', 'payment_status', 'cashier',
                'period', 'date', 'month', 'year', 'from', 'to',
            ]),
            'stats' => [
                'total' => (clone $counted)->count(),
                'today' => Order::query()->whereNotIn('status', $pending)->whereDate('created_at', today())->count(),
                'kitchen' => Order::query()->whereIn('status', [
                    OrderStatus::New->value,
                    OrderStatus::Processing->value,
                    OrderStatus::Preparing->value,
                ])->count(),
            ],
        ]);
    }

    public function show(Order $order): View
    {
        abort_unless(auth()->user()->hasPermission('orders.view'), 403);

        return view('orders.show', [
            'order' => $order->load(['items.product', 'items.batch', 'payments', 'customer', 'outlet', 'table', 'user', 'discount']),
        ]);
    }

    public function status(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($request->user()->can('orders.manage') || $request->user()->can('orders.check'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:new,processing,preparing,ready,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['status'] === 'ready' && ! $order->allItemsReady()) {
            throw ValidationException::withMessages([
                'status' => 'Tandai semua item siap di Daftar item sebelum lanjut ke Siap.',
            ]);
        }

        $orders->transition($order, OrderStatus::from($data['status']), $data['notes'] ?? null);

        return back()->with('success', 'Status order diperbarui.');
    }
}
