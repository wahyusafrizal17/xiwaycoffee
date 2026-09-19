<?php

namespace Database\Seeders;

use App\Models\InviteGuest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InviteGuestSeeder extends Seeder
{
    public function run(): void
    {
        $guests = [
            'Pak Wahyu Saimanuddin, ST & Istri',
            'Bang Hafiz & Istri',
            'Pak Harris & Istri',
            'Pak Sukartono',
            'Pak Irfan & Partner',
            'Bang Ayi & Istri',
            'Bang Herman & Istri',
            'Bang Ardiansah & Partner',
            'Team 25 Racing',
            'Team MRZ',
            'Team MO',
            'Klikmedis',
            'Pak Sagiman & Istri',
            'Pak Riyanto & Istri',
            'Nur Ichsan & Keluarga',
            'Windi & Keluarga',
            'Luthfi & Istri',
            'Klikmedis & Team',
            'Tanpa Kamu Mana Asik Team',
            'Marco & Keluarga',
        ];

        foreach ($guests as $i => $name) {
            InviteGuest::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1],
            );
        }
    }
}
