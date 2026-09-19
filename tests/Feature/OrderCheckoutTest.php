<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\CustomerPoint;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\EscPosPrinter;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_order_number_is_mass_assignable(): void
    {
        $this->assertTrue((new Order)->isFillable('order_number'));
    }

    public function test_checkout_sends_to_kitchen_then_completes_when_served(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $service = app(OrderService::class);
        $stockBefore = (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity');

        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'customer_id' => $this->customer->id,
            'order_type' => OrderType::Pickup->value,
            'guest_count' => 1,
        ]);

        $this->assertSame(OrderStatus::Draft, $order->status);

        $item = $service->addItem($order, [
            'product_id' => $this->sellableProduct->id,
            'quantity' => 2,
        ]);

        $order = $service->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue(
            Payment::query()->where('order_id', $order->id)->where('status', 'paid')->exists()
        );

        $expectedSubtotal = 35000 * 2;
        $this->assertEquals($expectedSubtotal, (float) $order->subtotal);
        $this->assertEquals($expectedSubtotal + round($expectedSubtotal * 0.11, 2), (float) $order->grand_total);
        $this->assertEquals($stockBefore, (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity'));

        $order = $service->updateItemStatus($item->fresh(), 'served');

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertEquals($stockBefore - 2, (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity'));
        $this->assertTrue(
            InventoryMovement::query()
                ->where('outlet_id', $this->outlet->id)
                ->where('product_id', $this->sellableProduct->id)
                ->where('type', StockMovementType::Sale)
                ->where('reference_id', $order->id)
                ->exists()
        );

        $this->customer->refresh();
        $expectedPoints = (int) floor(((float) $order->grand_total) / 10000);
        $this->assertSame($expectedPoints, (int) $this->customer->points);
        $this->assertTrue(
            CustomerPoint::query()
                ->where('customer_id', $this->customer->id)
                ->where('order_id', $order->id)
                ->where('type', 'earn')
                ->exists()
        );
        $this->assertEquals((float) $order->grand_total, (float) $this->customer->total_transaction);
        $this->assertNotNull($this->customer->last_transaction_at);
    }

    public function test_dine_in_checkout_does_not_require_a_table(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $service = app(OrderService::class);
        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::DineIn->value,
        ]);
        $service->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $order = $service->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertNull($order->table_id);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_checkout_returns_without_customer_receipt_print_payload(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $drink = Product::query()->create([
            'sku' => 'PRD-TEST-DRINK',
            'name' => 'Espresso',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 25000,
            'cost' => 8000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);

        $service = app(OrderService::class);
        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $service->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $service->addItem($order->fresh(), ['product_id' => $drink->id, 'quantity' => 1]);

        $this->postJson(route('pos.checkout', $order), [
            'method' => 'cash',
            'tendered' => 200000,
        ])->assertOk()
            ->assertJsonStructure(['order', 'print_jobs', 'kitchen_whatsapp_sent'])
            ->assertJsonMissingPath('receipt_escpos')
            ->assertJsonMissingPath('prep_ticket_escpos');
    }

    public function test_escpos_receipt_is_raw_bytes_and_cuts_paper(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $service = app(OrderService::class);
        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $service->addItem($order, [
            'product_id' => $this->sellableProduct->id,
            'quantity' => 2,
        ]);
        $order = $service->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        $raw = app(EscPosPrinter::class)->receipt($order);

        $html = app(EscPosPrinter::class)->receiptHtml($order);

        $this->assertStringStartsWith("\x1B\x40", $raw);
        $this->assertStringContainsString($order->order_number, $raw);
        $this->assertStringContainsString("\x1D\x56\x00", $raw);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertLessThanOrEqual(180, app(EscPosPrinter::class)->receiptHeightMm($order));
    }
}
