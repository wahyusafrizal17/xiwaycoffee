<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class ProductOptionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_required_and_optional_options_affect_line_price(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $sugar = ProductOptionGroup::query()->create([
            'product_id' => $this->sellableProduct->id,
            'name' => 'Sugar',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 1,
        ]);
        $normal = ProductOption::query()->create([
            'product_option_group_id' => $sugar->id,
            'name' => 'Normal',
            'price_adjustment' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ProductOption::query()->create([
            'product_option_group_id' => $sugar->id,
            'name' => 'Less',
            'price_adjustment' => 0,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $addons = ProductOptionGroup::query()->create([
            'product_id' => $this->sellableProduct->id,
            'name' => 'Add-ons',
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 3,
            'sort_order' => 2,
        ]);
        $shot = ProductOption::query()->create([
            'product_option_group_id' => $addons->id,
            'name' => 'Extra shot',
            'price_adjustment' => 5000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(OrderService::class);
        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);

        $item = $service->addItem($order, [
            'product_id' => $this->sellableProduct->id,
            'quantity' => 1,
            'option_ids' => [$normal->id, $shot->id],
        ]);

        $base = (float) $this->sellableProduct->price;
        $this->assertEquals($base + 5000, (float) $item->unit_price);
        $this->assertStringContainsString('Normal', $item->name);
        $this->assertStringContainsString('Extra shot', $item->name);
        $this->assertSame(2, $item->options()->count());
    }

    public function test_missing_required_option_is_rejected(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $sugar = ProductOptionGroup::query()->create([
            'product_id' => $this->sellableProduct->id,
            'name' => 'Sugar',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 1,
        ]);
        ProductOption::query()->create([
            'product_option_group_id' => $sugar->id,
            'name' => 'Normal',
            'price_adjustment' => 0,
            'is_active' => true,
        ]);

        $service = app(OrderService::class);
        $order = $service->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->addItem($order, [
            'product_id' => $this->sellableProduct->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);
    }
}
