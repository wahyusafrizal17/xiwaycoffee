<?php

namespace App\Http\Controllers;

use App\Exports\GenericExport;
use App\Models\Outlet;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function sales(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $rows = $this->reports->sales($filters);

        return $this->respond('reports.sales', $request, $filters, $rows, [
            ['No. Order', 'Outlet', 'Pelanggan', 'Total', 'Status', 'Tanggal'],
        ], fn ($order) => [
            $order->order_number,
            $order->outlet?->name,
            $order->customer?->name ?? 'Walk-in',
            $order->grand_total,
            $order->status?->label() ?? $order->status?->value,
            $order->created_at?->format('d/m/Y H:i'),
        ], 'sales', [
            'stats' => $this->reports->salesStats($filters),
        ]);
    }

    public function products(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $rows = $this->reports->productSales($filters);

        return $this->respond('reports.products', $request, $filters, $rows, [
            ['Produk', 'Qty', 'Total'],
        ], fn ($row) => [$row->name, $row->qty, $row->total], 'products', [
            'stats' => $this->reports->productSalesStats($filters),
        ]);
    }

    public function inventory(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $request->all();
        $rows = $this->reports->inventoryValuation($filters['outlet_id'] ?? current_outlet_id());

        return $this->respond('reports.inventory', $request, $filters, $rows, [
            ['Produk', 'Outlet', 'Qty', 'Cost', 'Value'],
        ], fn ($row) => [
            $row->product?->name,
            $row->outlet?->name,
            $row->quantity,
            $row->product?->cost,
            (float) $row->quantity * (float) ($row->product?->cost ?? 0),
        ], 'inventory');
    }

    public function movements(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $rows = $this->reports->movements($filters);

        return $this->respond('reports.movements', $request, $filters, $rows, [
            ['Ref', 'Produk', 'Tipe', 'Qty', 'Before', 'After'],
        ], fn ($row) => [$row->reference_number, $row->product?->name, $row->type?->value, $row->quantity, $row->before_stock, $row->after_stock], 'movements');
    }

    public function production(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $rows = $this->reports->production($filters);

        return $this->respond('reports.production', $request, $filters, $rows, [
            ['No', 'Produk', 'Planned', 'Produced', 'Yield'],
        ], fn ($row) => [$row->number, $row->product?->name, $row->quantity_planned, $row->quantity_produced, $row->yield_percentage], 'production');
    }

    public function customers(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        $filters = $request->all();
        $rows = $this->reports->customers($filters);

        return $this->respond('reports.customers', $request, $filters, $rows, [
            ['Nama', 'Phone', 'Level', 'Poin', 'Total'],
        ], fn ($row) => [$row->name, $row->phone, $row->membership_level?->value, $row->points, $row->total_transaction], 'customers');
    }

    public function categories(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $rows = $this->reports->categorySales($filters);

        return $this->respond('reports.categories', $request, $filters, $rows, [
            ['Kategori', 'Qty', 'Total'],
        ], fn ($row) => [$row->name, $row->qty, $row->total], 'categories', [
            'stats' => $this->reports->categorySalesStats($filters),
        ]);
    }

    public function promo(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $promo = $this->reports->promoPerformance($filters);
        $payload = [
            'discounts' => $promo['discounts'],
            'bundles' => $promo['bundles'],
            'filters' => $filters,
            'outlets' => Outlet::query()->orderBy('name')->get(),
            'stats' => $this->reports->promoStats($filters),
            'exporting' => false,
        ];

        if ($request->export === 'xlsx' && $request->user()->hasPermission('reports.export')) {
            $data = $promo['discounts']->map(fn ($row) => [
                $row->name, $row->usage_count, $row->discount_total, $row->sales_total,
            ])->all();

            return Excel::download(new GenericExport(['Program', 'Pemakaian', 'Diskon', 'Penjualan'], $data), 'promo.xlsx');
        }

        if ($request->export === 'pdf' && $request->user()->hasPermission('reports.export')) {
            return Pdf::loadView('reports.promo', $payload + ['exporting' => true])->download('promo.pdf');
        }

        return view('reports.promo', $payload);
    }

    public function waste(Request $request): View|BinaryFileResponse|\Illuminate\Http\Response
    {
        $filters = $request->all() + $this->reports->range($request->from, $request->to, $request->period);
        $rows = $this->reports->waste($filters);

        return $this->respond('reports.waste', $request, $filters, $rows, [
            ['No', 'Produk', 'Qty', 'Alasan'],
        ], fn ($row) => [$row->number, $row->product?->name, $row->quantity, $row->reason?->value], 'waste');
    }

    protected function respond(string $view, Request $request, array $filters, $rows, array $headings, callable $map, string $name, array $extra = [])
    {
        $payload = [
            'rows' => $rows,
            'filters' => $filters,
            'outlets' => Outlet::query()->orderBy('name')->get(),
            ...$extra,
        ];

        if ($request->export === 'xlsx' && $request->user()->hasPermission('reports.export')) {
            $data = collect($rows->items())->map($map)->all();

            return Excel::download(new GenericExport($headings[0], $data), "{$name}.xlsx");
        }

        if ($request->export === 'pdf' && $request->user()->hasPermission('reports.export')) {
            return Pdf::loadView($view, $payload + ['exporting' => true])->download("{$name}.pdf");
        }

        return view($view, $payload + ['exporting' => false]);
    }
}
