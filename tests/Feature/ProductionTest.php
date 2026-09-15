<?php

namespace Tests\Feature;

use App\Enums\ProductionStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use App\Services\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_completing_production_consumes_raw_creates_batch_and_calculates_yield(): void
    {
        $this->actingAsAtOutlet($this->admin, $this->centralKitchen);

        $chickenBefore = (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->rawChicken->id)
            ->value('quantity');
        $seasoningBefore = (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->rawSeasoning->id)
            ->value('quantity');
        $finishedBefore = (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->marinatedChicken->id)
            ->value('quantity');

        $service = app(ProductionService::class);
        $order = $service->create([
            'outlet_id' => $this->centralKitchen->id,
            'product_id' => $this->marinatedChicken->id,
            'bom_id' => $this->bom->id,
            'quantity_planned' => 10,
            'production_date' => now()->toDateString(),
        ]);

        $this->assertSame(ProductionStatus::Draft, $order->status);
        $this->assertGreaterThan(0, $order->items()->count());

        $completed = $service->complete($order, 9.5);

        $this->assertSame(ProductionStatus::Completed, $completed->status);
        $this->assertEquals(9.5, (float) $completed->quantity_produced);
        $this->assertEquals(95, (float) $completed->yield_percentage);
        $this->assertNotEmpty($completed->batch_number);

        $this->assertTrue(
            ProductionBatch::query()
                ->where('production_order_id', $completed->id)
                ->where('product_id', $this->marinatedChicken->id)
                ->exists()
        );

        $scale = 9.5 / 10;
        $this->assertEquals($chickenBefore - (10 * $scale), (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->rawChicken->id)
            ->value('quantity'));
        $this->assertEqualsWithDelta($seasoningBefore - (0.5 * $scale), (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->rawSeasoning->id)
            ->value('quantity'), 0.0001);
        $this->assertEquals($finishedBefore + 9.5, (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->marinatedChicken->id)
            ->value('quantity'));

        $this->assertTrue(
            InventoryMovement::query()
                ->where('type', StockMovementType::Production)
                ->where('outlet_id', $this->centralKitchen->id)
                ->where('product_id', $this->rawChicken->id)
                ->where('reference_id', $completed->id)
                ->exists()
        );
        $this->assertTrue(
            InventoryMovement::query()
                ->where('type', StockMovementType::Production)
                ->where('outlet_id', $this->centralKitchen->id)
                ->where('product_id', $this->marinatedChicken->id)
                ->where('reference_id', $completed->id)
                ->exists()
        );
    }
}
