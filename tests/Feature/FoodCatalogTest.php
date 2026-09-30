<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class FoodCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_food_role_manages_food_products_and_commission_stays_automatic(): void
    {
        $user = $this->makeUser('Irma', 'irma@test.local', 'food', [$this->outlet]);
        $food = Product::query()->create([
            'sku' => 'FOOD-NAS',
            'name' => 'Nasi Goreng',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 20000,
            'consignment_commission' => 2000,
            'is_sellable' => true,
            'is_active' => true,
            'station' => 'kitchen',
        ]);

        $this->actingAsAtOutlet($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Nasi Goreng')
            ->assertSee('10%')
            ->assertDontSee($this->sellableProduct->name);

        $this->actingAsAtOutlet($user)
            ->post(route('products.store'), [
                'sku' => 'FOOD-PEC',
                'name' => 'Pecel',
                'category_id' => $this->foodCategory->id,
                'unit_id' => $this->unitPcs->id,
                'type' => 'raw',
                'price' => 15000,
                'consignment_commission' => 0,
                'is_stockable' => 1,
            ])
            ->assertRedirect(route('products.index'));

        $created = Product::query()->where('sku', 'FOOD-PEC')->first();
        $this->assertNotNull($created);
        $this->assertGreaterThan(0, (float) $created->consignment_commission);
        $this->assertSame(ProductType::Finished, $created->type);
        $this->assertFalse($created->is_stockable);

        $this->actingAsAtOutlet($user)
            ->put(route('products.update', $this->sellableProduct), [
                'sku' => $this->sellableProduct->sku,
                'name' => 'Hacked',
                'unit_id' => $this->unitPcs->id,
                'type' => 'finished',
                'price' => 1000,
            ])
            ->assertForbidden();

        $this->actingAsAtOutlet($user)
            ->delete(route('products.destroy', $food))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($food);
        $this->assertSame('Chicken Burger', $this->sellableProduct->fresh()->name);
    }
}
