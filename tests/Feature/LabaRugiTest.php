<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\OperatingExpense;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class LabaRugiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_admin_can_open_laba_rugi_and_see_real_totals(): void
    {
        $this->sellableProduct->update(['cost' => 10000, 'price' => 35000]);

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 100000,
        ]);

        OperatingExpense::query()->create([
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->admin->id,
            'spent_on' => now()->toDateString(),
            'category' => ExpenseCategory::Rent->value,
            'amount' => 5000,
            'notes' => 'Sewa test',
        ]);

        $this->actingAsAtOutlet($this->cashier)
            ->get(route('reports.laba-rugi'))
            ->assertForbidden();

        $this->actingAsAtOutlet($this->admin)
            ->get(route('reports.laba-rugi', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Laba Rugi')
            ->assertSee('Penjualan')
            ->assertSee('HPP')
            ->assertSee('Laba kotor')
            ->assertSee('Laba bersih')
            ->assertSee('Sewa');
    }

    public function test_sidebar_has_single_bop_and_laba_rugi_link(): void
    {
        $html = $this->actingAsAtOutlet($this->admin)
            ->get(route('reports.laba-rugi'))
            ->assertOk()
            ->assertSee('Laba Rugi')
            ->assertSee('Keuangan')
            ->assertSee('Operasional')
            ->assertSee('Laporan')
            ->getContent();

        $this->assertSame(1, preg_match_all('#href="[^"]*/reports/expenses"#', $html));
        $this->assertGreaterThanOrEqual(1, preg_match_all('#/reports/laba-rugi#', $html));
        $this->assertSame(1, preg_match_all('#href="[^"]*/reports/profit"#', $html));
    }
}
