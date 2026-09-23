<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Pendapatan cafe dari makanan')
            ->assertSee('Setoran makanan')
            ->assertSee('Bagi hasil')
            ->assertSee('Pendapatan bersih')
            ->assertSee(money(18000))
            ->assertSee(money(15000))
            ->assertSee(money(2000))
            ->assertSee(money(13000))
            ->assertSee(money(5000))
            ->assertSee('Wahyu');

        $this->actingAsAtOutlet($this->admin)
            ->get(route('dashboard', ['period' => 'month', 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee(now()->locale('id')->translatedFormat('F Y'))
            ->assertSee('Target omzet minuman')
            ->assertSee('%');
    }

    public function test_cashier_dashboard_hides_profit_share(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('dashboard', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Ringkasan operasional')
            ->assertSee('Pendapatan kotor')
            ->assertSee('Pesanan berjalan')
            ->assertDontSee('Bagi hasil')
            ->assertDontSee('Pendapatan bersih')
            ->assertDontSee('Breakdown penjualan')
            ->assertDontSee('Wahyu');
    }
}
