<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Investor;
use App\Models\InvestorTopup;
use App\Models\Order;
use App\Services\ProfitShareService;
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

        Order::query()->create([
            'order_number' => 'ORD-TARGET-1',
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->admin->id,
            'channel' => OrderChannel::Pos,
            'order_type' => OrderType::DineIn,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 12_500_000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'grand_total' => 12_500_000,
        ]);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('investors.index'))
            ->assertOk()
            ->assertSee('50.000.000', false)
            ->assertSee('12.500.000', false)
            ->assertSee('25,0%', false);
    }
}
