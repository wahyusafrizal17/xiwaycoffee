<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use App\Services\DiscountService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PromoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_seeder_creates_guest_and_grand_opening_discounts(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(PromoSeeder::class);

        $guest = Discount::query()->where('code', 'TAMU-UNDANGAN')->first();
        $opening = Discount::query()->where('code', 'GRAND-OPENING')->with('items')->first();

        $this->assertNotNull($guest);
        $this->assertSame(100.0, (float) $guest->value);
        $this->assertSame('order', $guest->scope);

        $this->assertNotNull($opening);
        $this->assertSame(50.0, (float) $opening->value);
        $this->assertSame('category', $opening->scope);
        $this->assertCount(4, $opening->items);

        $service = app(DiscountService::class);
        $this->assertSame(50000.0, $service->calculate($guest, 50000));

        $drink = Product::query()->whereHas('category', fn ($q) => $q->where('name', 'Coffee'))->where('is_sellable', true)->first();
        $food = Product::query()->whereHas('category', fn ($q) => $q->where('name', 'Makanan'))->where('is_sellable', true)->first();
        $this->assertNotNull($drink);
        $this->assertNotNull($food);

        $amount = $service->calculate($opening, 50000, [
            ['product_id' => $drink->id, 'quantity' => 1, 'unit_price' => 20000],
            ['product_id' => $food->id, 'quantity' => 1, 'unit_price' => 30000],
        ]);

        $this->assertSame(10000.0, $amount);
    }
}
