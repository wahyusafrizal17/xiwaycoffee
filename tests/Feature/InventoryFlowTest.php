<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Enums\WasteReason;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use App\Services\WasteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_waste_decreases_stock(): void
    {
        $this->actingAsAtOutlet($this->admin);

        $before = (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity');

        $waste = app(WasteService::class)->record([
            'outlet_id' => $this->outlet->id,
            'product_id' => $this->sellableProduct->id,
            'quantity' => 2,
            'reason' => WasteReason::Spoiled->value,
            'notes' => 'Test waste',
        ]);

        $after = (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity');

        $this->assertEquals($before - 2, $after);
        $this->assertTrue(
            InventoryMovement::query()
                ->where('type', StockMovementType::Waste)
                ->where('reference_id', $waste->id)
                ->where('product_id', $this->sellableProduct->id)
                ->exists()
        );
    }

    public function test_stock_opname_finalize_creates_adjustment(): void
    {
        $this->actingAsAtOutlet($this->admin);

        $opnames = app(StockOpnameService::class);
        $opname = $opnames->create(['outlet_id' => $this->outlet->id]);

        $item = $opname->items->firstWhere('product_id', $this->sellableProduct->id);
        $this->assertNotNull($item);

        $before = (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity');

        $opnames->updateItems($opname, [[
            'id' => $item->id,
            'physical_qty' => $before + 3,
            'reason' => 'Found extra',
        ]]);

        $finalized = $opnames->finalize($opname->fresh(['items']));

        $this->assertSame('finalized', $finalized->status);
        $this->assertEquals($before + 3, (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->sellableProduct->id)
            ->value('quantity'));

        $this->assertTrue(
            InventoryMovement::query()
                ->where('type', StockMovementType::Adjustment)
                ->where('reference_id', $finalized->id)
                ->where('product_id', $this->sellableProduct->id)
                ->exists()
        );
    }

    public function test_transfer_ship_decreases_source_and_receive_increases_destination(): void
    {
        $this->actingAsAtOutlet($this->admin);

        $sourceBefore = (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->rawChicken->id)
            ->value('quantity');
        $destBefore = (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->rawChicken->id)
            ->value('quantity');

        $transfers = app(StockTransferService::class);
        $transfer = $transfers->create([
            'source_outlet_id' => $this->centralKitchen->id,
            'destination_outlet_id' => $this->outlet->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->rawChicken->id, 'quantity' => 8],
            ],
        ]);

        $transfers->approve($transfer);
        $shipped = $transfers->ship($transfer->fresh());
        $this->assertSame(TransferStatus::Shipped, $shipped->status);

        $this->assertEquals($sourceBefore - 8, (float) Inventory::query()
            ->where('outlet_id', $this->centralKitchen->id)
            ->where('product_id', $this->rawChicken->id)
            ->value('quantity'));

        $completed = $transfers->receive($shipped->fresh());
        $this->assertSame(TransferStatus::Completed, $completed->status);

        $this->assertEquals($destBefore + 8, (float) Inventory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('product_id', $this->rawChicken->id)
            ->value('quantity'));

        $this->assertSame(2, InventoryMovement::query()
            ->where('type', StockMovementType::Transfer)
            ->where('reference_id', $completed->id)
            ->count());
    }
}
