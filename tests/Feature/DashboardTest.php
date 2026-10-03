<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Services\DashboardService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_dashboard_shows_finance_breakdown_and_profit_share(): void
    {
        $drinkCategory = Category::query()->create([
            'name' => 'Coffee',
            'slug' => 'coffee-dash',
            'station' => PrinterStation::Bar->value,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $drink = Product::query()->create([
            'sku' => 'PRD-DRINK-DASH',
            'name' => 'Sanger Classic',
            'category_id' => $drinkCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 18000,
            'cost' => 5000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);

        $food = Product::query()->create([
            'sku' => 'PRD-FOOD-DASH',
            'name' => 'Ayam Pecak',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 15000,
            'cost' => 0,
            'consignment_commission' => 2000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
        ]);

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $drink->id, 'quantity' => 1]);
        $orders->addItem($order->fresh(), ['product_id' => $food->id, 'quantity' => 1]);
        $order = $orders->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        $this->actingAsAtOutlet($this->admin)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => 'sewa',
                'amount' => 5000,
                'notes' => 'Sewa dashboard',
                'evidence' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $this->actingAsAtOutlet($this->admin)
            ->get(route('dashboard', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Harian')
            ->assertSee('Bulanan')
            ->assertSee('Tahunan')
            ->assertSee('Range tanggal')
            ->assertSee('Terapkan')
            ->assertSee('Pendapatan minuman')
            ->assertSee('Pendapatan makanan')
            ->assertSee('Charge')
            ->assertSee('tidak masuk bagi hasil')
            ->assertSee('Pendapatan cafe dari makanan')
            ->assertSee('Setoran makanan')
            ->assertSee('Bagi hasil')
            ->assertSee('Pendapatan bersih')
            ->assertSee(money(18000))
            ->assertSee(money(15000))
            ->assertSee(money(1500))
            ->assertSee(money(13500))
            ->assertSee(money(5000))
            ->assertSee('Wahyu')
            ->assertSee('Cash')
            ->assertSee('QRIS')
            ->assertDontSee('Metode bayar')
            ->assertDontSee('>Kategori</h2>', false);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('dashboard', ['period' => 'month', 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee(strtolower(now()->locale('id')->translatedFormat('F Y')))
            ->assertDontSee('Target omzet');
    }

    public function test_cashier_dashboard_hides_profit_share(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('dashboard', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Pendapatan kotor')
            ->assertSee('Jumlah charge')
            ->assertSee('Cash')
            ->assertSee('QRIS')
            ->assertDontSee('Bagi hasil')
            ->assertDontSee('Pendapatan bersih')
            ->assertDontSee('Breakdown penjualan')
            ->assertDontSee('Wahyu');
    }

    public function test_dashboard_uses_recorded_prices_not_september_promo_cuts(): void
    {
        $food = Product::query()->create([
            'sku' => 'PRD-FOOD-HALF',
            'name' => 'Ayam Half',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 20000,
            'consignment_commission' => 2000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Kitchen->value,
        ]);

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $food->id, 'quantity' => 1]);
        $order = $orders->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);
        $order->forceFill(['created_at' => '2026-09-20 12:00:00'])->save();

        $metrics = app(DashboardService::class)->metrics($this->outlet->id, [
            'period' => 'day',
            'from' => '2026-09-20',
            'to' => '2026-09-20',
            'label' => '20 Sep 2026',
        ]);

        $this->assertEquals(20000.0, $metrics['food_sales']);
        $this->assertEquals(2000.0, $metrics['food_cafe']);
        $this->assertEquals(18000.0, $metrics['food_setoran']);
        $this->assertEquals(0.0, $metrics['service_fee']);
        $this->assertEquals(2000.0, $metrics['sales']);
        $this->assertEquals(2000.0, $metrics['share_base']);
        $this->assertEquals($metrics['gross'] - $metrics['bop'], $metrics['net']);
    }

    public function test_dashboard_keeps_paid_orders_and_reconciles_payments(): void
    {
        $drinkCategory = Category::query()->create([
            'name' => 'Coffee',
            'slug' => 'coffee-recon',
            'station' => PrinterStation::Bar->value,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $drink = Product::query()->create([
            'sku' => 'PRD-DRINK-RECON',
            'name' => 'Sanger Recon',
            'category_id' => $drinkCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 20000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);

        $paid = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($paid, ['product_id' => $drink->id, 'quantity' => 1]);
        $paid = $orders->checkout($paid->fresh(), [
            'method' => PaymentMethod::Qris->value,
            'tendered' => 100000,
        ]);
        $paid->forceFill(['created_at' => '2026-09-30 23:50:00'])->save();

        $cancelled = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($cancelled, ['product_id' => $drink->id, 'quantity' => 1]);
        $cancelled = $orders->checkout($cancelled->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);
        $cancelled->forceFill([
            'status' => OrderStatus::Cancelled->value,
            'created_at' => '2026-09-15 10:00:00',
        ])->save();

        $outside = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($outside, ['product_id' => $drink->id, 'quantity' => 1]);
        $outside = $orders->checkout($outside->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);
        $outside->forceFill(['created_at' => '2026-08-31 12:00:00'])->save();
        $outside->payments()->update(['created_at' => '2026-09-02 08:00:00']);

        Payment::query()->create([
            'order_id' => $paid->id,
            'user_id' => $this->cashier->id,
            'method' => PaymentMethod::Qris->value,
            'amount' => $paid->grand_total,
            'tendered' => $paid->grand_total,
            'change_amount' => 0,
            'status' => 'paid',
        ]);

        $metrics = app(DashboardService::class)->metrics($this->outlet->id, [
            'period' => 'month',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'label' => 'September 2026',
        ]);

        $this->assertEquals((float) $paid->grand_total, $metrics['gross']);
        $this->assertSame(1, $metrics['orders']);
        $this->assertEquals($metrics['gross'] - $metrics['bop'], $metrics['net']);
        $this->assertEquals((float) $paid->grand_total * 2, $metrics['qris']);
        $this->assertEquals(0.0, $metrics['cash']);
        $this->assertEquals((float) $paid->grand_total * 2, $metrics['payment_total']);
        $this->assertFalse($metrics['payments_match']);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('dashboard', ['period' => 'month', 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Perlu rekonsiliasi', false);
    }
}
