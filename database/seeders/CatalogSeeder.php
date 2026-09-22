<?php

namespace Database\Seeders;

use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Bom;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'PCS', 'name' => 'Pieces', 'family' => 'count', 'conversion_factor' => 1],
            ['code' => 'POR', 'name' => 'Portion', 'family' => 'count', 'conversion_factor' => 1],
            ['code' => 'KG', 'name' => 'Kilogram', 'family' => 'weight', 'conversion_factor' => 1000],
            ['code' => 'G', 'name' => 'Gram', 'family' => 'weight', 'conversion_factor' => 1],
            ['code' => 'L', 'name' => 'Liter', 'family' => 'volume', 'conversion_factor' => 1000],
            ['code' => 'ML', 'name' => 'Milliliter', 'family' => 'volume', 'conversion_factor' => 1],
        ] as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], $unit);
        }

        $categories = [
            ['name' => 'Coffee', 'station' => PrinterStation::Bar->value, 'color' => '#6f1715', 'sort_order' => 1],
            ['name' => 'Non Coffee', 'station' => PrinterStation::Bar->value, 'color' => '#92400e', 'sort_order' => 2],
            ['name' => 'Fit Tea', 'station' => PrinterStation::Bar->value, 'color' => '#0f766e', 'sort_order' => 3],
            ['name' => 'Xiway Main', 'station' => PrinterStation::Bar->value, 'color' => '#111111', 'sort_order' => 4],
            ['name' => 'Makanan', 'station' => PrinterStation::Kitchen->value, 'color' => '#c2410c', 'sort_order' => 5],
            ['name' => 'Mie', 'station' => PrinterStation::Kitchen->value, 'color' => '#b45309', 'sort_order' => 6],
            ['name' => 'Snack', 'station' => PrinterStation::Kitchen->value, 'color' => '#a16207', 'sort_order' => 7],
            ['name' => 'Ingredients', 'station' => PrinterStation::Bar->value, 'color' => '#64748b', 'sort_order' => 8],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                $category + ['is_active' => true],
            );
        }

        Category::query()
            ->whereNotIn('slug', collect($categories)->map(fn ($row) => Str::slug($row['name'])))
            ->update(['is_active' => false]);

        Product::query()->where('is_sellable', true)->update(['is_active' => false, 'is_sellable' => false]);

        $unit = fn (string $code) => Unit::query()->where('code', $code)->firstOrFail()->id;
        $category = fn (string $name) => Category::query()->where('name', $name)->firstOrFail();
        $commission = (float) config('pos.food_commission', 2000);

        foreach ([
            // Harga final hasil survey Cimahi; HPP dari sheet resep (food cost ideal ≤35%)
            ['name' => 'Sanger Classic', 'price' => 18000, 'cost' => 5157, 'star' => true],
            ['name' => 'Americano', 'price' => 18000, 'cost' => 5850],
            ['name' => 'Coffee Latte', 'price' => 20000, 'cost' => 6200],
            ['name' => 'Kopi Susu Classic', 'price' => 21000, 'cost' => 7585],
            ['name' => 'Kopi Susu Xiway', 'price' => 22000, 'cost' => 7559, 'star' => true],
            ['name' => 'Kosuren', 'price' => 22000, 'cost' => 6815],
            ['name' => 'Double Sanger', 'price' => 23000, 'cost' => 8500, 'star' => true],
            ['name' => 'Butterscotch Latte', 'price' => 25000, 'cost' => 9335],
            ['name' => 'Vanilla Latte', 'price' => 25000, 'cost' => 9335],
            ['name' => 'Tiramisu Latte', 'price' => 25000, 'cost' => 9335],
            ['name' => 'Caramel Latte', 'price' => 25000, 'cost' => 9335],
            ['name' => 'Dirty Latte', 'price' => 25000, 'cost' => 8218],
        ] as $item) {
            $this->drink($item['name'], $category('Coffee'), $unit('PCS'), $item['price'], $item['cost'], $item['star'] ?? false);
        }

        foreach ([
            ['name' => 'Chocolate', 'price' => 20000, 'cost' => 6670],
            ['name' => 'Red Velvet', 'price' => 20000, 'cost' => 6492],
            ['name' => 'Taro', 'price' => 20000, 'cost' => 6492],
            ['name' => 'Strawberry Mist', 'price' => 22000, 'cost' => 7137],
            ['name' => 'Creamy Cookie', 'price' => 24000, 'cost' => 8252],
            ['name' => 'Matcha', 'price' => 25000, 'cost' => 8085],
            ['name' => 'Avocado', 'price' => 25000, 'cost' => 8420],
            ['name' => 'Air Mineral', 'price' => 5000, 'cost' => 2500],
        ] as $item) {
            $this->drink($item['name'], $category('Non Coffee'), $unit('PCS'), $item['price'], $item['cost'], $item['star'] ?? false);
        }

        foreach ([
            ['name' => 'Peach Tea', 'price' => 15000, 'cost' => 3697],
            ['name' => 'Lychee Tea', 'price' => 15000, 'cost' => 3697],
            ['name' => 'Strawberry Tea', 'price' => 15000, 'cost' => 3697],
        ] as $item) {
            $this->drink($item['name'], $category('Fit Tea'), $unit('PCS'), $item['price'], $item['cost'], $item['star'] ?? false);
        }

        foreach ([
            ['name' => 'Scarlet Brew', 'price' => 24000, 'cost' => 6015],
            ['name' => 'Dark Berry', 'price' => 25000, 'cost' => 7660],
            ['name' => 'Passion Brew', 'price' => 22000, 'cost' => 5148],
            ['name' => 'Butterscotch Noir', 'price' => 28000, 'cost' => 9835, 'star' => true],
            ['name' => 'Golden Cream Latte', 'price' => 28000, 'cost' => 9835, 'star' => true],
            ['name' => 'Xiway Sea Salt', 'price' => 29000, 'cost' => 10000, 'star' => true],
        ] as $item) {
            $this->drink($item['name'], $category('Xiway Main'), $unit('PCS'), $item['price'], $item['cost'], $item['star'] ?? false);
        }

        foreach ([
            ['name' => 'Ayam Pecak', 'price' => 15000],
            ['name' => 'Ayam Sambal Matah', 'price' => 15000],
            ['name' => 'Nasi Ayam Pecak', 'price' => 25000, 'star' => true],
            ['name' => 'Nasi Ayam Sambal Matah', 'price' => 25000, 'star' => true],
            ['name' => 'Nasi Ayam Penyet', 'price' => 25000],
            ['name' => 'Nasi Telur Pecak', 'price' => 17000],
            ['name' => 'Nasi Telur Sambal Matah', 'price' => 17000],
            ['name' => 'Nasi Soto Ayam', 'price' => 27000],
            ['name' => 'Nasi Ayam Sambal Geprek', 'price' => 23000],
            ['name' => 'Nasi Ayam Cabai Ijo', 'price' => 23000],
            ['name' => 'Nasi Goreng Special', 'price' => 27000, 'star' => true],
            ['name' => 'Nasi Goreng Hijau Telur', 'price' => 20000],
            ['name' => 'Nasi Goreng Hijau Ayam', 'price' => 27000],
            ['name' => 'Nasi Goreng Merah Telur', 'price' => 20000],
            ['name' => 'Nasi Goreng Merah Ayam', 'price' => 25000],
            ['name' => 'Nasi Goreng Hijau Ayam Pecak', 'price' => 28000, 'star' => true],
            ['name' => 'Nasi Goreng Merah Ayam Sambal Matah', 'price' => 28000],
            ['name' => 'Nasi Nila Pecak', 'price' => 29000],
            ['name' => 'Nasi Nila Sambal Matah', 'price' => 30000],
            ['name' => 'Nasi Lele Pecak', 'price' => 23000],
            ['name' => 'Nasi Lele Sambal Matah', 'price' => 23000],
        ] as $item) {
            $this->food($item['name'], $category('Makanan'), $unit('POR'), $item['price'], $commission, $item['star'] ?? false);
        }

        foreach ([
            ['name' => 'Mie Goreng Telur', 'price' => 15000],
            ['name' => 'Mie Pedas Gila Telur', 'price' => 15000, 'star' => true],
            ['name' => 'Indomie Original', 'price' => 11000],
            ['name' => 'Indomie Original Telur', 'price' => 14000],
        ] as $item) {
            $this->food($item['name'], $category('Mie'), $unit('POR'), $item['price'], $commission, $item['star'] ?? false);
        }

        $mieStyles = ['Goreng', 'Tumis', 'Kuah'];
        foreach ([
            ['name' => 'Mie Aceh Biasa', 'price' => 17000],
            ['name' => 'Mie Aceh Telur', 'price' => 20000],
            ['name' => 'Mie Aceh Daging', 'price' => 25000, 'star' => true],
            ['name' => 'Mie Aceh Udang', 'price' => 25000],
            ['name' => 'Mie Aceh Cumi', 'price' => 25000],
            ['name' => 'Indomie Aceh Biasa', 'price' => 17000],
            ['name' => 'Indomie Aceh Telur', 'price' => 20000],
            ['name' => 'Indomie Aceh Daging', 'price' => 25000, 'star' => true],
            ['name' => 'Indomie Aceh Udang', 'price' => 25000],
            ['name' => 'Indomie Aceh Cumi', 'price' => 25000],
        ] as $item) {
            $product = $this->food($item['name'], $category('Mie'), $unit('POR'), $item['price'], $commission, $item['star'] ?? false);
            foreach ($mieStyles as $style) {
                $product->variants()->updateOrCreate(
                    ['name' => $style],
                    ['sku' => $this->sku($item['name'].'-'.$style), 'price_adjustment' => 0, 'is_active' => true],
                );
            }
        }

        foreach ([
            ['name' => 'French Fries', 'price' => 15000],
            ['name' => 'Nugget', 'price' => 15000],
            ['name' => 'Sosis', 'price' => 15000],
            ['name' => 'Mix (Kentang, Nugget & Sosis)', 'price' => 23000],
            ['name' => 'Dimsum', 'price' => 15000],
            ['name' => 'Lumpia', 'price' => 15000],
            ['name' => 'Lumpia Keju', 'price' => 15000],
            ['name' => 'Ekado Telur', 'price' => 15000],
            ['name' => 'Dimsum Nori Roll', 'price' => 15000],
            ['name' => 'Dimsum Mozarella', 'price' => 15000],
            ['name' => 'Udang Crispy', 'price' => 15000],
        ] as $item) {
            $this->food($item['name'], $category('Snack'), $unit('POR'), $item['price'], $commission);
        }

        $keepFoodNames = collect([
            'Ayam Pecak', 'Ayam Sambal Matah', 'Nasi Ayam Pecak', 'Nasi Ayam Sambal Matah', 'Nasi Ayam Penyet',
            'Nasi Telur Pecak', 'Nasi Telur Sambal Matah', 'Nasi Soto Ayam', 'Nasi Ayam Sambal Geprek', 'Nasi Ayam Cabai Ijo',
            'Nasi Goreng Special', 'Nasi Goreng Hijau Telur', 'Nasi Goreng Hijau Ayam', 'Nasi Goreng Merah Telur', 'Nasi Goreng Merah Ayam',
            'Nasi Goreng Hijau Ayam Pecak', 'Nasi Goreng Merah Ayam Sambal Matah',
            'Nasi Nila Pecak', 'Nasi Nila Sambal Matah', 'Nasi Lele Pecak', 'Nasi Lele Sambal Matah',
            'Mie Goreng Telur', 'Mie Pedas Gila Telur', 'Indomie Original', 'Indomie Original Telur',
            'Mie Aceh Biasa', 'Mie Aceh Telur', 'Mie Aceh Daging', 'Mie Aceh Udang', 'Mie Aceh Cumi',
            'Indomie Aceh Biasa', 'Indomie Aceh Telur', 'Indomie Aceh Daging', 'Indomie Aceh Udang', 'Indomie Aceh Cumi',
            'French Fries', 'Nugget', 'Sosis', 'Mix (Kentang, Nugget & Sosis)', 'Dimsum', 'Lumpia', 'Lumpia Keju',
            'Ekado Telur', 'Dimsum Nori Roll', 'Dimsum Mozarella', 'Udang Crispy',
        ]);

        Product::query()
            ->where('station', PrinterStation::Kitchen->value)
            ->where('is_sellable', true)
            ->whereNotIn('name', $keepFoodNames)
            ->update(['is_active' => false, 'is_sellable' => false]);

        foreach ([
            ['name' => 'Coffee Bean', 'unit' => 'KG', 'cost' => 280000, 'reorder' => 5, 'min' => 2, 'max' => 80],
            ['name' => 'Milk', 'unit' => 'L', 'cost' => 20000, 'reorder' => 10, 'min' => 5, 'max' => 150],
            ['name' => 'Creamer', 'unit' => 'KG', 'cost' => 80000, 'reorder' => 2, 'min' => 1, 'max' => 40],
            ['name' => 'Sugar', 'unit' => 'KG', 'cost' => 16000, 'reorder' => 8, 'min' => 4, 'max' => 150],
        ] as $item) {
            $this->product($this->sku($item['name']), $item['name'], $category('Ingredients'), $unit($item['unit']), [
                'type' => ProductType::Raw,
                'price' => 0,
                'cost' => $item['cost'],
                'station' => PrinterStation::Bar->value,
                'is_sellable' => false,
                'is_stockable' => true,
                'is_active' => true,
                'minimum_stock' => $item['min'],
                'reorder_level' => $item['reorder'],
                'maximum_stock' => $item['max'],
            ]);
        }

        Product::query()
            ->whereIn('name', ['Coffee Bean', 'Milk', 'Creamer', 'Sugar'])
            ->where('sku', 'not like', 'XIW-%')
            ->update(['is_active' => false]);

        Product::query()
            ->where('type', ProductType::Raw->value)
            ->whereNotIn('name', ['Coffee Bean', 'Milk', 'Creamer', 'Sugar'])
            ->update(['is_active' => false]);

        Product::query()->where('type', ProductType::SemiFinished->value)->update(['is_active' => false]);
        Product::query()->where('type', ProductType::Package->value)->update(['is_active' => false, 'is_sellable' => false]);

        $this->seedDrinkRecipe('Sanger Classic', [
            ['name' => 'Coffee Bean', 'quantity' => 0.018],
            ['name' => 'Milk', 'quantity' => 0.12],
            ['name' => 'Creamer', 'quantity' => 0.015],
        ]);
        $this->seedDrinkRecipe('Double Sanger', [
            ['name' => 'Coffee Bean', 'quantity' => 0.036],
            ['name' => 'Milk', 'quantity' => 0.12],
            ['name' => 'Creamer', 'quantity' => 0.015],
        ]);

        // BOM applyToProduct overwrites cost — keep sheet HPP as source of truth for now.
        Product::query()->where('name', 'Sanger Classic')->update(['cost' => 5157]);
        Product::query()->where('name', 'Double Sanger')->update(['cost' => 8500]);
    }

    protected function drink(string $name, Category $category, int $unitId, float $price, float $cost = 0, bool $star = false): Product
    {
        return $this->product($this->sku($name), $name, $category, $unitId, [
            'type' => ProductType::Finished,
            'price' => $price,
            'cost' => $cost,
            'consignment_commission' => 0,
            'station' => PrinterStation::Bar->value,
            'prep_minutes' => 6,
            'description' => $name,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'is_recommended' => $star,
        ]);
    }

    protected function food(string $name, Category $category, int $unitId, float $price, float $commission, bool $star = false): Product
    {
        return $this->product($this->sku($name), $name, $category, $unitId, [
            'type' => ProductType::Finished,
            'price' => $price,
            'cost' => 0,
            'consignment_commission' => $commission,
            'station' => PrinterStation::Kitchen->value,
            'prep_minutes' => 12,
            'description' => null,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'is_recommended' => $star,
        ]);
    }

    protected function product(string $sku, string $name, Category $category, int $unitId, array $attrs): Product
    {
        return Product::query()->updateOrCreate(
            ['sku' => $sku],
            array_merge([
                'name' => $name,
                'category_id' => $category->id,
                'unit_id' => $unitId,
                'bom_level' => 0,
                'is_active' => true,
            ], $attrs),
        );
    }

    /**
     * @param  array<int, array{name: string, quantity: float}>  $items
     */
    protected function seedDrinkRecipe(string $productName, array $items): void
    {
        $product = Product::query()->where('name', $productName)->where('is_active', true)->firstOrFail();
        $bom = Bom::query()->updateOrCreate(
            ['product_id' => $product->id, 'version' => '1.0'],
            [
                'yield_percentage' => 100,
                'waste_percentage' => 0,
                'is_active' => true,
                'notes' => 'Resep HPP '.$product->name,
            ],
        );

        $bom->items()->delete();
        foreach ($items as $item) {
            $component = Product::query()->where('name', $item['name'])->where('is_active', true)->firstOrFail();
            $bom->items()->create([
                'component_id' => $component->id,
                'unit_id' => $component->unit_id,
                'quantity' => $item['quantity'],
                'waste_percentage' => 0,
                'yield_percentage' => 100,
            ]);
        }

        $bom->applyToProduct();
    }

    protected function sku(string $name): string
    {
        return 'XIW-'.strtoupper(Str::slug($name, '-'));
    }
}
