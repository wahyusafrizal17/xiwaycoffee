<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Bundle;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class BundlePromoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_weekday_combo_shows_automatic_normal_price_and_only_the_chosen_item(): void
    {
        $tea = $this->menu('Fit Tea', 15000);
        $dimsum = $this->menu('Dimsum', 15000);
        $nugget = $this->menu('Nugget', 15000);

        $this->actingAsAtOutlet($this->admin)
            ->post(route('marketing.bundles.store'), [
                'name' => 'Combo 2',
                'campaign' => 'After School',
                'price' => 22000,
                'start_time' => '14:00',
                'end_time' => '18:00',
                'weekdays_only' => 1,
                'requirement' => 'Tunjukkan seragam / kartu pelajar',
                'items' => [
                    ['product_id' => $tea->id, 'quantity' => 1],
                    ['product_id' => $dimsum->id, 'quantity' => 1, 'choice_group' => 'snack'],
                    ['product_id' => $nugget->id, 'quantity' => 1, 'choice_group' => 'snack'],
                ],
            ])
            ->assertRedirect();

        $this->travelTo(Carbon::parse('2026-10-10 15:00:00', 'Asia/Jakarta'));
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertDontSee('Combo 2');

        $this->travelTo(Carbon::parse('2026-10-06 15:00:00', 'Asia/Jakarta'));
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('After School')
            ->assertSee('Combo 2')
            ->assertSee(money(30000))
            ->assertSee(money(22000))
            ->assertSee('Tunjukkan seragam / kartu pelajar');

        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => 'pickup',
        ]);
        $bundleId = Bundle::query()->where('name', 'Combo 2')->value('id');

        $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('pos.items.store', $order), [
                'product_id' => $tea->id,
                'bundle_id' => $bundleId,
            ])
            ->assertStatus(422);

        $item = $orders->addItem($order->fresh(), [
            'product_id' => $tea->id,
            'bundle_id' => $bundleId,
            'bundle_picks' => [$dimsum->id],
        ]);
        $this->assertSame('Combo 2 · Dimsum', $item->name);
        $this->assertEquals(22000, (float) $item->unit_price);

        $order = $orders->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 50000,
        ]);
        $orders->updateItemStatus($item->fresh(), 'served');

        $this->assertEquals(9, $this->stock($tea));
        $this->assertEquals(9, $this->stock($dimsum));
        $this->assertEquals(10, $this->stock($nugget));
    }

    protected function menu(string $name, int $price): Product
    {
        $product = Product::query()->create([
            'sku' => 'PRD-'.str($name)->slug(),
            'name' => $name,
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => $price,
            'is_sellable' => true,
            'is_stockable' => true,
            'is_active' => true,
            'station' => PrinterStation::Kitchen->value,
        ]);
        Inventory::query()->create([
            'outlet_id' => $this->outlet->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        return $product;
    }

    protected function stock(Product $product): float
    {
        return (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $product->id)
            ->value('quantity');
    }
}
