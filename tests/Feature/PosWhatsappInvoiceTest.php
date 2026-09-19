<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class PosWhatsappInvoiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();

        config([
            'services.wacloud.base_url' => 'https://app.wacloud.id/api/v1',
            'services.wacloud.api_key' => 'test-key',
            'services.wacloud.device_id' => 'device-1',
        ]);
    }

    public function test_checkout_asks_for_whatsapp_invoice_without_receipt_print_payload(): void
    {
        Http::fake([
            'app.wacloud.id/*' => Http::response(['success' => true, 'data' => ['status' => 'pending']], 200),
        ]);

        Setting::query()->updateOrCreate(
            ['outlet_id' => null, 'key' => 'kitchen_whatsapp'],
            ['value' => '081234567890', 'group' => 'general'],
        );

        $this->sellableProduct->update(['station' => PrinterStation::Kitchen->value]);

        $order = $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('pos.draft'), [
                'order_type' => 'pickup',
                'channel' => 'pickup',
            ])
            ->json('id') ?? null;

        $this->assertNotNull($order);

        $this->postJson(route('pos.items.store', $order), [
            'product_id' => $this->sellableProduct->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson(route('pos.checkout', $order), [
            'method' => 'cash',
            'amount' => 25000,
            'tendered' => 25000,
        ]);

        $response->assertOk()
            ->assertJsonPath('kitchen_whatsapp_sent', true)
            ->assertJsonMissingPath('receipt_escpos');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/devices/device-1/messages')
                && $request['to'] === '6281234567890'
                && str_contains($request['message'], 'PESANAN DAPUR');
        });
    }

    public function test_admin_can_send_customer_invoice_whatsapp(): void
    {
        Http::fake([
            'app.wacloud.id/*' => Http::response(['success' => true, 'data' => ['status' => 'pending']], 200),
        ]);

        config(['app.url' => 'http://localhost']);

        $order = Order::query()->create([
            'order_number' => 'ORD-WA-1',
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->cashier->id,
            'channel' => 'pos',
            'order_type' => 'pickup',
            'status' => 'completed',
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 20000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'grand_total' => 20000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->sellableProduct->id,
            'name' => $this->sellableProduct->name,
            'quantity' => 1,
            'unit_price' => 20000,
            'total' => 20000,
        ]);

        $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('pos.invoice.whatsapp', $order), ['phone' => '081298765432'])
            ->assertOk()
            ->assertJson(['ok' => true, 'via' => 'text']);

        Http::assertSent(function ($request) {
            return $request['to'] === '6281298765432'
                && $request['message_type'] === 'text'
                && str_contains($request['message'], 'ORD-WA-1')
                && str_contains($request['message'], 'Total:');
        });
    }

    public function test_public_app_url_sends_invoice_pdf_document(): void
    {
        Http::fake([
            'app.wacloud.id/*' => Http::response(['success' => true, 'data' => ['status' => 'pending']], 200),
        ]);

        config(['app.url' => 'https://pos.example.com']);

        $order = Order::query()->create([
            'order_number' => 'ORD-WA-PDF-SEND',
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->cashier->id,
            'channel' => 'pos',
            'order_type' => 'pickup',
            'status' => 'completed',
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 20000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'grand_total' => 20000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->sellableProduct->id,
            'name' => $this->sellableProduct->name,
            'quantity' => 1,
            'unit_price' => 20000,
            'total' => 20000,
        ]);

        $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('pos.invoice.whatsapp', $order), ['phone' => '081298765432'])
            ->assertOk()
            ->assertJson(['ok' => true, 'via' => 'pdf']);

        Http::assertSent(function ($request) use ($order) {
            return $request['to'] === '6281298765432'
                && $request['message_type'] === 'document'
                && ! empty($request['document_url'])
                && str_contains($request['document_url'], '/pos/'.$order->id.'/invoice.pdf')
                && str_contains((string) $request['filename'], 'ORD-WA-PDF-SEND');
        });
    }

    public function test_signed_invoice_pdf_is_downloadable(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-WA-PDF',
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->cashier->id,
            'channel' => 'pos',
            'order_type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 20000,
            'discount_amount' => 0,
            'tax_amount' => 2000,
            'tax_rate' => 10,
            'service_charge' => 0,
            'grand_total' => 22000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->sellableProduct->id,
            'name' => $this->sellableProduct->name,
            'quantity' => 1,
            'unit_price' => 20000,
            'total' => 20000,
        ]);

        $url = URL::temporarySignedRoute('pos.invoice.pdf', now()->addHour(), ['order' => $order->id]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
