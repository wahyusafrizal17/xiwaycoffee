<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class OrderShowInvoiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_order_show_hides_check_flow_and_shows_whatsapp_invoice_for_paid(): void
    {
        $service = app(OrderService::class);
        $this->actingAsAtOutlet($this->cashier);

        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $service->addItem($order, [
            'product_id' => $this->sellableProduct->id,
            'quantity' => 1,
        ]);
        $order = $service->checkout($order->fresh(), [
            'method' => 'cash',
            'tendered' => 100000,
        ]);

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('Alur pengerjaan')
            ->assertDontSee('Tandai siap')
            ->assertDontSee('Lanjut:')
            ->assertSee('Nomor WhatsApp')
            ->assertSee('invoice-whatsapp', false);
    }

    public function test_open_order_show_has_no_whatsapp_block(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $order = Order::query()->create([
            'order_number' => 'ORD-TEST-OPEN',
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->cashier->id,
            'order_type' => OrderType::Pickup,
            'status' => OrderStatus::Draft,
            'payment_status' => PaymentStatus::Unpaid,
            'tax_rate' => 11,
            'subtotal' => 0,
            'grand_total' => 0,
        ]);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('Alur pengerjaan')
            ->assertDontSee('Kirim invoice WhatsApp')
            ->assertDontSee('Nomor WhatsApp');
    }
}
