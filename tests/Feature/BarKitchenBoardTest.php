<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class BarKitchenBoardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_submitted_food_appears_on_bar_and_kitchen_boards(): void
    {
        $order = $this->submitFoodAndDrink();

        $this->actingAsAtOutlet($this->cashier)
            ->get(route('bar.index'))
            ->assertOk()
            ->assertSee('Chicken Burger')
            ->assertSee('Es Teh')
            ->assertSee($order->order_number);

        $this->actingAsAtOutlet($this->cashier)
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertSee('Chicken Burger')
            ->assertSee($order->order_number)
            ->assertDontSee('Es Teh');
    }

    public function test_food_cannot_be_served_until_kitchen_marks_ready(): void
    {
        $order = $this->submitFoodAndDrink();
        $food = $order->items()->where('product_id', $this->sellableProduct->id)->first();

        $this->actingAsAtOutlet($this->cashier)
            ->from(route('kitchen.index'))
            ->post(route('order-items.status', $food), ['status' => 'served'])
            ->assertForbidden();

        $this->actingAsAtOutlet($this->cashier)
            ->post(route('order-items.status', $food), ['status' => 'preparing'])
            ->assertRedirect();

        $this->actingAsAtOutlet($this->cashier)
            ->post(route('order-items.status', $food->fresh()), ['status' => 'ready'])
            ->assertRedirect();

        $this->actingAsAtOutlet($this->cashier)
            ->post(route('order-items.status', $food->fresh()), ['status' => 'served'])
            ->assertRedirect();

        $this->assertSame('served', $food->fresh()->status);
    }

    public function test_cashier_still_prepares_drinks(): void
    {
        $order = $this->submitFoodAndDrink();
        $drink = $order->items()->where('name', 'Es Teh')->first();

        $this->actingAsAtOutlet($this->cashier)
            ->post(route('order-items.status', $drink), ['status' => 'preparing'])
            ->assertRedirect();

        $this->assertSame('preparing', $drink->fresh()->status);
    }

    protected function submitFoodAndDrink()
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::DineIn->value,
            'table_id' => $this->tableA->id,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->addItem($order->fresh(), ['product_id' => $this->makeDrink()->id, 'quantity' => 1]);

        return $orders->submit($order->fresh());
    }

    protected function makeDrink(): Product
    {
        $category = Category::query()->create([
            'name' => 'Beverage',
            'slug' => 'beverage-bar-board',
            'station' => PrinterStation::Bar->value,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'sku' => 'PRD-TEST-DRINK',
            'name' => 'Es Teh',
            'category_id' => $category->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 8000,
            'cost' => 2000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);
    }
}
