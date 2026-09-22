<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\DiscountService;
use App\Services\EscPosPrinter;
use App\Services\OrderService;
use App\Services\PrinterRoutingService;
use App\Services\TableService;
use App\Services\WaCloudService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use RuntimeException;

class PosController extends Controller
{
    public function index(Request $request, DiscountService $discounts): View
    {
        abort_unless($request->user()->hasPermission('pos.access'), 403);

        $outletId = current_outlet_id();

        $products = Product::query()->sellable()->with(['category', 'unit', 'variants', 'optionGroups.options'])->orderBy('name')->get();

        return view('pos.index', [
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($q) => $q->sellable())
                ->orderBy('sort_order')
                ->get(),
            'products' => $products,
            'bundles' => Bundle::query()
                ->where('is_active', true)
                ->with(['product', 'items.product', 'outlets'])
                ->get()
                ->filter(fn (Bundle $bundle) => $bundle->isCurrentlyActive($outletId))
                ->values(),
            'discounts' => $activeDiscounts = $discounts->activeForOutlet($outletId),
            'discountCatalog' => $activeDiscounts->map(fn ($discount) => [
                'id' => $discount->id,
                'name' => $discount->name,
                'value_label' => $discount->valueLabel(),
                'minimum' => (float) $discount->minimum_transaction,
                'maximum' => $discount->maximum_discount !== null ? (float) $discount->maximum_discount : null,
            ])->values(),
            'heldOrders' => $this->heldOrdersQuery($outletId)
                ->get()
                ->map(fn (Order $order) => $this->heldOrderPayload($order))
                ->values(),
            'productImages' => $products->mapWithKeys(fn ($product) => [(string) $product->id => $product->imageUrl()]),
            'productCatalog' => $products->mapWithKeys(fn (Product $product) => [
                (string) $product->id => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'option_groups' => $product->optionGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'is_required' => (bool) $group->is_required,
                        'min_select' => (int) $group->min_select,
                        'max_select' => (int) $group->max_select,
                        'options' => $group->options->map(fn ($option) => [
                            'id' => $option->id,
                            'name' => $option->name,
                            'price_adjustment' => (float) $option->price_adjustment,
                            'is_active' => (bool) $option->is_active,
                        ])->values(),
                    ])->values(),
                ],
            ]),
            'qzPrinter' => setting('qz_printer', ''),
        ]);
    }

    public function draft(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'order_type' => ['required', 'in:dine_in,pickup,online'],
            'channel' => ['nullable', 'in:pos,online,pickup'],
            'table_id' => ['nullable', 'exists:tables,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['outlet_id'] = current_outlet_id();
        $data['channel'] = $data['channel'] ?? match ($data['order_type']) {
            'pickup' => 'pickup',
            'online' => 'online',
            default => 'pos',
        };

        return response()->json($orders->createDraft($data)->load(['items.product', 'table', 'customer']));
    }

    public function addItem(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'bundle_id' => ['nullable', 'exists:bundles,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:product_options,id'],
        ]);

        $orders->addItem($order, $data);

        return response()->json($order->fresh(['items.product', 'items.options', 'customer', 'table']));
    }

    public function updateItem(Request $request, Order $order, $item, OrderService $orders): JsonResponse
    {
        $line = $order->items()->whereKey($item)->firstOrFail();
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($orders->updateItem($line, $data));
    }

    public function removeItem(Order $order, $item, OrderService $orders): JsonResponse
    {
        $line = $order->items()->whereKey($item)->firstOrFail();

        return response()->json($orders->removeItem($line));
    }

    public function discount(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'discount_id' => ['nullable', 'exists:discounts,id'],
            'manual' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($orders->applyDiscount($order, $data['discount_id'] ?? null, (float) ($data['manual'] ?? 0)));
    }

    public function held(): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('pos.access'), 403);

        return response()->json(
            $this->heldOrdersQuery(current_outlet_id())
                ->get()
                ->map(fn (Order $order) => $this->heldOrderPayload($order))
                ->values()
        );
    }

    public function hold(Order $order, OrderService $orders): JsonResponse
    {
        abort_unless((int) $order->outlet_id === (int) current_outlet_id(), 403);

        return response()->json($this->heldOrderPayload($orders->hold($order)));
    }

    public function recall(Order $order): JsonResponse
    {
        abort_unless((int) $order->outlet_id === (int) current_outlet_id(), 403);
        abort_unless(in_array($order->status, [OrderStatus::Held, OrderStatus::Draft], true), 422);

        return response()->json($order->load(['items.product', 'customer', 'table', 'discount']));
    }

    public function points(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json($orders->applyPoints($order, (int) $data['points']));
    }

    public function customer(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        return response()->json($orders->assignCustomer($order, $data['customer_id'] ?? null));
    }

    public function submit(Request $request, Order $order, OrderService $orders, PrinterRoutingService $printers, WaCloudService $wa): JsonResponse
    {
        abort_unless((int) $order->outlet_id === (int) current_outlet_id(), 403);

        $data = $request->validate([
            'order_type' => ['nullable', 'in:dine_in,pickup,online'],
            'table_id' => ['nullable', 'exists:tables,id'],
        ]);

        $submitted = $orders->submit($order, $data)->load(['items.product', 'customer', 'table', 'outlet', 'user']);
        $kitchenWa = $this->notifyKitchenWhatsApp($submitted, $printers, $wa);

        return response()->json([
            'order' => $submitted,
            'print_jobs' => $this->escposJobs($this->printJobsForQz($printers->route($submitted), $kitchenWa), $submitted),
            'kitchen_whatsapp_sent' => $kitchenWa,
            'qz_printer' => setting('qz_printer', ''),
        ]);
    }

    public function transfer(Request $request, Order $order, TableService $tables, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'exists:tables,id'],
        ]);

        if ($order->table_id && (int) $order->table_id !== (int) $data['table_id']) {
            $tables->transfer((int) $order->table_id, (int) $data['table_id']);
        } elseif (! $order->table_id) {
            $orders->assignTable($order, (int) $data['table_id']);
        }

        return response()->json($order->fresh(['items.product', 'customer', 'table']));
    }

    public function checkout(Request $request, Order $order, OrderService $orders, PrinterRoutingService $printers, WaCloudService $wa): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders.checkout'), 403);

        $data = $request->validate([
            'method' => ['required', 'in:cash,qris'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'order_type' => ['nullable', 'in:dine_in,pickup,online'],
            'table_id' => ['nullable', 'exists:tables,id'],
        ]);

        $completed = $orders->checkout($order, $data)->load(['items.product', 'payments', 'outlet', 'customer', 'table', 'user']);
        $kitchenWa = $this->notifyKitchenWhatsApp($completed, $printers, $wa);

        return response()->json([
            'order' => $completed,
            'kitchen_whatsapp_sent' => $kitchenWa,
            // Customer invoice goes via WhatsApp modal — no receipt print.
            'print_jobs' => $this->escposJobs($this->printJobsForQz($printers->route($completed), $kitchenWa), $completed),
            'qz_printer' => setting('qz_printer', ''),
        ]);
    }

    public function sendInvoiceWhatsapp(Request $request, Order $order, WaCloudService $wa): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders.checkout'), 403);
        abort_unless((int) $order->outlet_id === (int) current_outlet_id(), 403);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $order->load(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        $via = 'text';
        $notice = null;

        try {
            if ($this->invoicePdfIsPubliclyReachable()) {
                $filename = 'Invoice-'.$order->order_number.'.pdf';
                $pdfUrl = URL::temporarySignedRoute(
                    'pos.invoice.pdf',
                    now()->addHours(6),
                    ['order' => $order->id],
                );
                $wa->sendDocument($data['phone'], $pdfUrl, $filename, $wa->invoiceCaption($order));
                $via = 'pdf';
            } else {
                $wa->sendText($data['phone'], $wa->invoiceMessage($order));
                $notice = 'Invoice dikirim sebagai teks (APP_URL lokal tidak bisa diambil WACloud untuk PDF).';
            }
        } catch (RuntimeException $e) {
            // Document quota / API failure → text receipt so checkout isn't blocked.
            try {
                $wa->sendText($data['phone'], $wa->invoiceMessage($order));
                $via = 'text';
                $notice = $e->getMessage().' Invoice dikirim sebagai teks.';
            } catch (RuntimeException $textError) {
                return response()->json(['message' => $textError->getMessage()], 422);
            }
        }

        return response()->json(['ok' => true, 'via' => $via, 'notice' => $notice]);
    }

    public function invoicePdf(Order $order): Response
    {
        $order->load(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        return Pdf::loadView('pos.invoice-pdf', ['order' => $order])
            ->setPaper('a5')
            ->stream('Invoice-'.$order->order_number.'.pdf');
    }

    protected function invoicePdfIsPubliclyReachable(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $orders->cancel($order, $data['reason']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Order dibatalkan.');
    }

    public function receipt(Order $order): View
    {
        $order->load(['items', 'payments', 'outlet', 'customer', 'table', 'user']);

        return view('pos.receipt', [
            'order' => $order,
            'receiptEscpos' => base64_encode(app(EscPosPrinter::class)->receipt($order)),
            'receiptHtml' => app(EscPosPrinter::class)->receiptHtml($order),
            'receiptHeightMm' => app(EscPosPrinter::class)->receiptHeightMm($order),
            'qzPrinter' => setting('qz_printer', ''),
        ]);
    }

    public function ticket(Request $request, Order $order, string $station): View
    {
        abort_unless(in_array($station, ['cashier', 'kitchen', 'bar', 'prep'], true), 404);

        $order->load(['items.product', 'items.variant', 'outlet', 'table', 'user', 'customer']);
        $items = $order->items->filter(function ($item) use ($station) {
            $itemStation = $item->station ?? $item->product?->station ?? $item->product?->category?->station;

            return in_array($station, ['cashier', 'prep'], true) || $itemStation === $station;
        });

        return view('pos.ticket', [
            'order' => $order,
            'station' => $station,
            'items' => $items,
            'reprint' => $request->boolean('reprint'),
        ]);
    }

    protected function heldOrdersQuery(?int $outletId)
    {
        return Order::query()
            ->where('outlet_id', $outletId)
            ->where('status', OrderStatus::Held)
            ->with(['table', 'customer', 'items'])
            ->latest('held_at')
            ->latest();
    }

    protected function heldOrderPayload(Order $order): array
    {
        $order->loadMissing(['table', 'customer', 'items']);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'grand_total' => (float) $order->grand_total,
            'order_type' => $order->order_type?->value,
            'order_type_label' => $order->order_type?->label(),
            'table' => $order->table?->code,
            'customer' => $order->customer?->name,
            'items_count' => $order->items->count(),
            'held_at' => $order->held_at?->toIso8601String(),
            'status' => $order->status?->value,
        ];
    }

    protected function escposJobs(array $jobs, $order): array
    {
        $escpos = app(EscPosPrinter::class);

        return array_map(function (array $job) use ($escpos, $order) {
            $station = $job['payload']['station'] ?? 'cashier';
            $job['escpos'] = base64_encode($escpos->ticket($order, $station, $job['items']));
            $job['html'] = $escpos->ticketHtml($order, $station, $job['items']);
            $job['height_mm'] = $escpos->ticketHeightMm($job['items']);

            return $job;
        }, $jobs);
    }

    protected function notifyKitchenWhatsApp(Order $order, PrinterRoutingService $printers, WaCloudService $wa): bool
    {
        try {
            return $wa->sendKitchenOrder($order, $printers);
        } catch (RuntimeException $e) {
            report($e);

            return false;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $jobs
     * @return list<array<string, mixed>>
     */
    protected function printJobsForQz(array $jobs, bool $kitchenViaWhatsApp): array
    {
        if (! $kitchenViaWhatsApp) {
            return $jobs;
        }

        return array_values(array_filter(
            $jobs,
            fn (array $job) => ($job['payload']['station'] ?? '') !== 'kitchen',
        ));
    }
}
