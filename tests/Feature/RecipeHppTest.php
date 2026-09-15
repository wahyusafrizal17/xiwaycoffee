<?php

namespace Tests\Feature;

use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Bom;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class RecipeHppTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_recipe_cost_uses_ingredient_qty_and_unit_conversion(): void
    {
        $this->rawChicken->update(['cost' => 100000]);
        $this->rawSeasoning->update(['cost' => 40000]);

        $this->assertEquals(102000, $this->bom->fresh()->recipeCost());
    }

    public function test_saving_bom_writes_hpp_to_the_menu(): void
    {
        $this->actingAsAtOutlet($this->admin);
        $this->rawChicken->update(['cost' => 100000]);
        $this->rawSeasoning->update(['cost' => 40000]);

        $this->put(route('boms.update', $this->bom), [
            'product_id' => $this->marinatedChicken->id,
            'version' => '1.0',
            'yield_percentage' => 100,
            'waste_percentage' => 0,
            'is_active' => 1,
            'items' => [
                ['component_id' => $this->rawChicken->id, 'unit_id' => $this->unitKg->id, 'quantity' => 1, 'waste_percentage' => 0, 'yield_percentage' => 100],
                ['component_id' => $this->rawSeasoning->id, 'unit_id' => $this->unitKg->id, 'quantity' => 0.05, 'waste_percentage' => 0, 'yield_percentage' => 100],
            ],
        ])->assertRedirect();

        $this->assertEquals(102000, (float) $this->marinatedChicken->fresh()->cost);
    }

    public function test_updating_ingredient_cost_refreshes_menu_hpp(): void
    {
        $this->actingAsAtOutlet($this->admin);
        $this->rawChicken->update(['cost' => 100000]);
        $this->rawSeasoning->update(['cost' => 40000]);
        $this->bom->applyToProduct();

        $this->put(route('products.update', $this->rawChicken), [
            'sku' => $this->rawChicken->sku,
            'name' => $this->rawChicken->name,
            'category_id' => $this->rawChicken->category_id,
            'unit_id' => $this->rawChicken->unit_id,
            'type' => ProductType::Raw->value,
            'price' => 0,
            'cost' => 120000,
            'station' => PrinterStation::Kitchen->value,
            'is_sellable' => 0,
            'is_stockable' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertEquals(122000, (float) $this->marinatedChicken->fresh()->cost);
    }

    public function test_sanger_recipe_hpp_from_coffee_milk_creamer(): void
    {
        $unitL = \App\Models\Unit::query()->create([
            'code' => 'L',
            'name' => 'Liter',
            'family' => 'volume',
            'conversion_factor' => 1000,
        ]);
        $coffee = Product::query()->create([
            'sku' => 'RAW-COFFEE',
            'name' => 'Coffee Bean',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitKg->id,
            'type' => ProductType::Raw,
            'price' => 0,
            'cost' => 280000,
            'is_sellable' => false,
            'is_stockable' => true,
            'is_active' => true,
        ]);
        $milk = Product::query()->create([
            'sku' => 'RAW-MILK',
            'name' => 'Milk',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $unitL->id,
            'type' => ProductType::Raw,
            'price' => 0,
            'cost' => 20000,
            'is_sellable' => false,
            'is_stockable' => true,
            'is_active' => true,
        ]);
        $creamer = Product::query()->create([
            'sku' => 'RAW-CREAMER',
            'name' => 'Creamer',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitKg->id,
            'type' => ProductType::Raw,
            'price' => 0,
            'cost' => 80000,
            'is_sellable' => false,
            'is_stockable' => true,
            'is_active' => true,
        ]);
        $sanger = Product::query()->create([
            'sku' => 'PRD-SANGER-TEST',
            'name' => 'Sanger',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 22000,
            'cost' => 0,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);

        $bom = Bom::query()->create([
            'product_id' => $sanger->id,
            'version' => '1.0',
            'yield_percentage' => 100,
            'waste_percentage' => 0,
            'is_active' => true,
        ]);
        $bom->items()->create(['component_id' => $coffee->id, 'unit_id' => $coffee->unit_id, 'quantity' => 0.018, 'waste_percentage' => 0, 'yield_percentage' => 100]);
        $bom->items()->create(['component_id' => $milk->id, 'unit_id' => $milk->unit_id, 'quantity' => 0.12, 'waste_percentage' => 0, 'yield_percentage' => 100]);
        $bom->items()->create(['component_id' => $creamer->id, 'unit_id' => $creamer->unit_id, 'quantity' => 0.015, 'waste_percentage' => 0, 'yield_percentage' => 100]);
        $bom->applyToProduct();

        $this->assertEquals(8640, (float) $sanger->fresh()->cost);
        $this->assertCount(3, $sanger->fresh()->recipeLines());
    }
}
