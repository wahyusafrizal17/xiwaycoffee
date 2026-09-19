<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Models\Category;
use App\Models\Discount;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        Discount::query()->where('code', 'WEEKDAY10')->each(function (Discount $discount) {
            $discount->items()->delete();
            $discount->outlets()->detach();
            $discount->forceDelete();
        });

        $guest = Discount::query()->updateOrCreate(
            ['code' => 'TAMU-UNDANGAN'],
            [
                'name' => 'Tamu Undangan',
                'type' => DiscountType::Percentage,
                'scope' => 'order',
                'value' => 100,
                'minimum_transaction' => 0,
                'maximum_discount' => null,
                'start_date' => null,
                'end_date' => null,
                'start_time' => null,
                'end_time' => null,
                'is_active' => true,
            ],
        );
        $guest->items()->delete();

        $opening = Discount::query()->updateOrCreate(
            ['code' => 'GRAND-OPENING'],
            [
                'name' => 'Grand Opening',
                'type' => DiscountType::Percentage,
                'scope' => 'category',
                'value' => 50,
                'minimum_transaction' => 0,
                'maximum_discount' => null,
                'start_date' => null,
                'end_date' => null,
                'start_time' => null,
                'end_time' => null,
                'is_active' => true,
            ],
        );

        $drinkCategoryIds = Category::query()
            ->whereIn('name', ['Coffee', 'Non Coffee', 'Fit Tea', 'Xiway Main'])
            ->pluck('id');

        $opening->items()->delete();
        foreach ($drinkCategoryIds as $categoryId) {
            $opening->items()->create(['category_id' => $categoryId]);
        }
    }
}
