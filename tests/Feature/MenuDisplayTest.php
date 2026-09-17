<?php

namespace Tests\Feature;

use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_menu_display_matches_template_style_and_data(): void
    {
        $unit = Unit::query()->create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'family' => 'count',
            'conversion_factor' => 1,
        ]);

        $coffee = Category::query()->create([
            'name' => 'Coffee',
            'slug' => 'coffee',
            'station' => PrinterStation::Bar->value,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $makanan = Category::query()->create([
            'name' => 'Makanan',
            'slug' => 'makanan',
            'station' => PrinterStation::Kitchen->value,
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $mie = Category::query()->create([
            'name' => 'Mie',
            'slug' => 'mie',
            'station' => PrinterStation::Kitchen->value,
            'sort_order' => 6,
            'is_active' => true,
        ]);

        Product::query()->create([
            'sku' => 'XIW-SANGER',
            'name' => 'Sanger Classic',
            'category_id' => $coffee->id,
            'unit_id' => $unit->id,
            'type' => ProductType::Finished,
            'price' => 18000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'is_recommended' => false,
            'station' => PrinterStation::Bar->value,
        ]);

        Product::query()->create([
            'sku' => 'XIW-PECAK',
            'name' => 'Nasi Ayam Pecak',
            'category_id' => $makanan->id,
            'unit_id' => $unit->id,
            'type' => ProductType::Finished,
            'price' => 25000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'is_recommended' => true,
            'station' => PrinterStation::Kitchen->value,
        ]);

        Product::query()->create([
            'sku' => 'XIW-MIE-ACEH',
            'name' => 'Mie Aceh Biasa',
            'category_id' => $mie->id,
            'unit_id' => $unit->id,
            'type' => ProductType::Finished,
            'price' => 17000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'is_recommended' => true,
            'station' => PrinterStation::Kitchen->value,
        ]);

        Bundle::query()->create([
            'name' => 'Paket Nasi + Sanger',
            'sku' => 'BND-TEST',
            'price' => 38000,
            'is_active' => true,
        ]);

        $this->get('/display')
            ->assertOk()
            ->assertSee('Cormorant Garamond', false)
            ->assertDontSee('Menu Lengkap')
            ->assertSee('"title":"Menu"', false)
            ->assertDontSee('"title":"Minuman"', false)
            ->assertSee('Sanger Classic')
            ->assertSee('Nasi Ayam Pecak')
            ->assertSee('Mie Aceh Biasa')
            ->assertSee('Paket Nasi + Sanger')
            ->assertSee('"name":"Makanan"', false)
            ->assertSee('"name":"Coffee"', false)
            ->assertSee('"name":"Mie"', false)
            ->assertSee('"name":"Paket Bundle"', false)
            ->assertDontSee('"name":"Indomie"', false)
            ->assertSee('"price":18', false)
            ->assertSee('"price":25', false)
            ->assertSee('"price":38', false)
            ->assertSee('"star":true', false)
            ->assertSee('sig-star', false)
            ->assertSee('--paper: #FFFFFF', false)
            ->assertSee('images/logo/xiway-logo.png', false)
            ->assertSee('watermark', false);
    }
}
