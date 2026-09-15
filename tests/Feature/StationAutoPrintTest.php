<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Printer;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class StationAutoPrintTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_new_order_queues_kitchen_and_bar_tickets(): void
    {
        $drink = $this->makeDrink();

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->addItem($order->fresh(), ['product_id' => $drink->id, 'quantity' => 2]);
        $orders->submit($order->fresh());

        $kitchen = $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'kitchen']))
            ->assertOk()
            ->assertJsonPath('station', 'kitchen')
            ->json('jobs');

        $this->assertCount(1, $kitchen);
        $this->assertSame($order->id, $kitchen[0]['order_id']);
        $this->assertNotEmpty($kitchen[0]['escpos']);

        $bar = $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'bar']))
            ->assertOk()
            ->assertJsonPath('station', 'bar')
            ->json('jobs');

        $this->assertCount(1, $bar);
        $this->assertSame($order->id, $bar[0]['order_id']);

        $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('print-jobs.ack', ['order' => $order, 'station' => 'kitchen']))
            ->assertOk();

        $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'kitchen']))
            ->assertOk()
            ->assertJsonCount(0, 'jobs');

        $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'bar']))
            ->assertOk()
            ->assertJsonCount(1, 'jobs');
    }

    public function test_food_only_order_does_not_queue_bar_ticket(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->submit($order->fresh());

        $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'bar']))
            ->assertOk()
            ->assertJsonCount(0, 'jobs');

        $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'kitchen']))
            ->assertOk()
            ->assertJsonCount(1, 'jobs');
    }

    public function test_print_jobs_require_a_station(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index'))
            ->assertForbidden();
    }

    public function test_network_send_does_not_mark_printed_when_printer_offline(): void
    {
        Printer::query()->create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Kitchen LAN',
            'station' => PrinterStation::Kitchen,
            'ip_address' => '127.0.0.1',
            'port' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $order = $orders->submit($order->fresh());

        app(\App\Services\PrinterRoutingService::class)->dispatchNetworkTickets($order->fresh());

        $this->assertNull($order->fresh()->kitchen_printed_at);

        $this->actingAsAtOutlet($this->cashier)
            ->getJson(route('print-jobs.index', ['station' => 'kitchen']))
            ->assertOk()
            ->assertJsonCount(1, 'jobs');
    }

    protected function makeDrink(): Product
    {
        $category = Category::query()->create([
            'name' => 'Beverage',
            'slug' => 'beverage-test',
            'station' => PrinterStation::Bar->value,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'sku' => 'PRD-TEST-DRINK',
            'name' => 'Es Teh',
            'category_id' => $category->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 8000,
            'cost' => 2000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);
    }
}
