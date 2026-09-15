<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\ProfitShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class ProfitShareTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_remainder_splits_by_capital_and_last_partner_gets_the_cents(): void
    {
        $shares = (new ProfitShareService)->split(750000, config('pos.partners'));

        $this->assertSame('Wahyu', $shares[0]['name']);
        $this->assertEquals(226890.76, $shares[0]['amount']);
        $this->assertSame('Rizky', $shares[1]['name']);
        $this->assertEquals(340336.13, $shares[1]['amount']);
        $this->assertSame('Johan', $shares[2]['name']);
        $this->assertEquals(182773.11, $shares[2]['amount']);
        $this->assertEquals(750000.0, round(array_sum(array_column($shares, 'amount')), 2));
    }

    public function test_profit_report_subtracts_bop_then_splits(): void
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
                'notes' => 'Sewa bulan ini',
            ])
            ->assertRedirect();

        $sales = (float) $order->grand_total;
        $remainder = $sales - 10000;

        $this->actingAsAtOutlet($this->admin)
            ->get(route('reports.profit', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Wahyu')
            ->assertSee('Rizky')
            ->assertSee('Johan')
            ->assertSee('Sewa bulan ini');

        $summary = app(ProfitShareService::class)->summarize([
            'outlet_id' => $this->outlet->id,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $this->assertEquals($sales, $summary['sales']);
        $this->assertEquals(10000.0, $summary['bop']);
        $this->assertEquals($remainder, $summary['remainder']);
        $this->assertEquals($remainder, round(array_sum(array_column($summary['shares'], 'amount')), 2));
    }

    public function test_cashier_cannot_open_profit_share(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('reports.profit'))
            ->assertForbidden();
    }

    public function test_food_setoran_excludes_vendor_share_from_cafe_omzet(): void
    {
        $food = Product::query()->create([
            'sku' => 'PRD-FOOD-CONS',
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

        $item = $order->items()->first();
        $this->assertEquals(2000.0, (float) $item->consignment_commission);
        $this->assertEquals(13000.0, (float) $item->total - ((float) $item->consignment_commission * (float) $item->quantity));

        $summary = app(ProfitShareService::class)->summarize([
            'outlet_id' => $this->outlet->id,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $this->assertEquals(13000.0, $summary['food_setoran']);
        $this->assertEquals(round((float) $order->grand_total - 13000, 2), $summary['sales']);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('reports.setoran', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Ayam Pecak');
    }

    public function test_catalog_seeder_marks_food_as_consignment(): void
    {
        $this->seed(\Database\Seeders\CatalogSeeder::class);

        $sanger = Product::query()->where('name', 'Sanger Classic')->where('is_active', true)->first();
        $this->assertNotNull($sanger);
        $this->assertFalse($sanger->is_stockable);
        $this->assertEquals(0.0, (float) $sanger->consignment_commission);

        $pecak = Product::query()->where('name', 'Ayam Pecak')->where('is_active', true)->first();
        $this->assertNotNull($pecak);
        $this->assertFalse($pecak->is_stockable);
        $this->assertEquals(2000.0, (float) $pecak->consignment_commission);
        $this->assertTrue($pecak->is_recommended === false);

        $nasi = Product::query()->where('name', 'Nasi Ayam Pecak')->where('is_active', true)->first();
        $this->assertTrue((bool) $nasi?->is_recommended);

        $mie = Product::query()->where('name', 'Mie Aceh Biasa')->where('is_active', true)->with('variants')->first();
        $this->assertEquals(['Goreng', 'Tumis', 'Kuah'], $mie?->variants->pluck('name')->all());
    }
}
