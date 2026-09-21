<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
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

    public function test_dashboard_shows_revenue_expense_and_net(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $order = $orders->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        $this->actingAsAtOutlet($this->admin)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => 'sewa',
                'amount' => 10000,
                'notes' => 'Sewa hari ini',
            ])
            ->assertRedirect();

        $gross = (float) $order->grand_total;
        $net = $gross - 10000;

        $this->actingAsAtOutlet($this->admin)
            ->get(route('dashboard', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Pendapatan')
            ->assertSee('Pengeluaran')
            ->assertSee('Omzet bersih')
            ->assertSee('Produk terlaris')
            ->assertSee($this->sellableProduct->name)
            ->assertSee(money($gross))
            ->assertSee(money(10000))
            ->assertSee(money($net));
    }
}
