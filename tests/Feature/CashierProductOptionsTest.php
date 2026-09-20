<?php

namespace Tests\Feature;

use App\Models\ProductOptionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class CashierProductOptionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_cashier_can_view_products_and_update_options_only(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee($this->sellableProduct->name);

        $this->actingAsAtOutlet($this->cashier)
            ->put(route('products.options.update', $this->sellableProduct), [
                'option_groups' => [
                    [
                        'name' => 'Level Pedas',
                        'is_required' => 1,
                        'min_select' => 1,
                        'max_select' => 1,
                        'options' => [
                            ['name' => 'Pedas', 'price_adjustment' => 0, 'is_active' => 1],
                            ['name' => 'Tidak Pedas', 'price_adjustment' => 0, 'is_active' => 1],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('product_option_groups', [
            'product_id' => $this->sellableProduct->id,
            'name' => 'Level Pedas',
        ]);
        $this->assertSame(2, ProductOptionGroup::query()
            ->where('product_id', $this->sellableProduct->id)
            ->where('name', 'Level Pedas')
            ->first()
            ->options()
            ->count());
    }

    public function test_cashier_cannot_update_product_fields(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->put(route('products.update', $this->sellableProduct), [
                'sku' => $this->sellableProduct->sku,
                'name' => 'Hacked Name',
                'unit_id' => $this->sellableProduct->unit_id,
                'type' => $this->sellableProduct->type->value,
                'price' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $this->sellableProduct->id,
            'name' => $this->sellableProduct->name,
        ]);
    }
}
