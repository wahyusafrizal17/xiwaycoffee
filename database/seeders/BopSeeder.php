<?php

namespace Database\Seeders;

use App\Models\MonthlyTarget;
use App\Models\Outlet;
use Illuminate\Database\Seeder;

class BopSeeder extends Seeder
{
    public function run(): void
    {
        $amount = monthly_bop();
        $year = (int) now()->year;
        $month = (int) now()->month;

        $outlets = Outlet::query()->where('is_active', true)->get();
        if ($outlets->isEmpty()) {
            return;
        }

        foreach ($outlets as $outlet) {
            MonthlyTarget::query()->updateOrCreate(
                [
                    'outlet_id' => $outlet->id,
                    'year' => $year,
                    'month' => $month,
                ],
                ['amount' => $amount],
            );
        }
    }
}
