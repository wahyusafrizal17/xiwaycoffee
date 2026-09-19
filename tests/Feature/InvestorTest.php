<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Investor;
use App\Models\InvestorTopup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ProfitShareService;
use Database\Seeders\BopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class InvestorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_admin_can_topup_investor_capital(): void
    {
        $investor = Investor::query()->create([
            'name' => 'Wahyu',
            'capital' => 90_000_000,
            'is_active' => true,
        ]);

        $this->actingAsAtOutlet($this->admin)
            ->post(route('investors.topup'), [
                'investor_id' => $investor->id,
                'topped_up_on' => now()->toDateString(),
                'amount' => 10_000_000,
                'notes' => 'Topup modal',
            ])
            ->assertRedirect(route('investors.index'));

        $this->assertEquals(100_000_000.0, (float) $investor->fresh()->capital);
        $this->assertDatabaseHas('investor_topups', [
            'investor_id' => $investor->id,
            'amount' => 10000000,
            'notes' => 'Topup modal',
        ]);
        $this->assertSame(1, InvestorTopup::query()->count());
    }

    public function test_profit_share_uses_investor_capital_from_database(): void
    {
        Investor::query()->create(['name' => 'A', 'capital' => 25_000_000, 'is_active' => true]);
        Investor::query()->create(['name' => 'B', 'capital' => 75_000_000, 'is_active' => true]);

        $shares = (new ProfitShareService)->split(1000, (new ProfitShareService)->partners());

        $this->assertSame('A', $shares[0]['name']);
        $this->assertEquals(250.0, $shares[0]['amount']);
        $this->assertSame('B', $shares[1]['name']);
        $this->assertEquals(750.0, $shares[1]['amount']);
    }

    public function test_cashier_cannot_open_investors(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('investors.index'))
            ->assertForbidden();
    }

    public function test_admin_can_set_monthly_sales_target(): void
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->actingAsAtOutlet($this->admin)
            ->post(route('investors.target'), [
                'year' => $year,
                'month' => $month,
                'amount' => 50_000_000,
            ])
            ->assertRedirect(route('investors.index'));

        $this->assertDatabaseHas('monthly_targets', [
            'outlet_id' => $this->outlet->id,
            'year' => $year,
            'month' => $month,
            'amount' => 50000000,
        ]);

        $drinkCategory = Category::query()->create([
            'name' => 'Coffee',
            'slug' => 'coffee-test',
            'station' => PrinterStation::Bar->value,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $drink = Product::query()->create([
            'sku' => 'PRD-DRINK-TARGET',
            'name' => 'Americano Test',
            'category_id' => $drinkCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 20000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);

        $order = Order::query()->create([
            'order_number' => 'ORD-TARGET-1',
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->admin->id,
            'channel' => OrderChannel::Pos,
            'order_type' => OrderType::DineIn,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 12_535_000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'grand_total' => 12_535_000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $drink->id,
            'name' => $drink->name,
            'quantity' => 625,
            'unit_price' => 20000,
            'total' => 12_500_000,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->sellableProduct->id,
            'name' => $this->sellableProduct->name,
            'quantity' => 1,
            'unit_price' => 35000,
            'total' => 35000,
        ]);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('investors.index'))
            ->assertOk()
            ->assertSee('50.000.000', false)
            ->assertSee('12.500.000', false)
            ->assertSee('25,0%', false)
            ->assertDontSee('12.535.000', false);
    }

    public function test_monthly_bop_covers_fixed_operating_costs(): void
    {
        $this->assertEquals(14_100_000.0, monthly_bop());

        $this->seed(BopSeeder::class);

        $this->assertDatabaseHas('monthly_targets', [
            'outlet_id' => $this->outlet->id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'amount' => 14100000,
        ]);
    }
}
