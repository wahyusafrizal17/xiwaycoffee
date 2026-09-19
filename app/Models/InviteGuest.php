<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'sort_order'])]
class InviteGuest extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function inviteUrl(): string
    {
        return route('invites.show', $this->slug);
    }

    public function whatsappShareUrl(): string
    {
        $name = $this->name;
        $inviteUrl = $this->inviteUrl();
        $mapsUrl = 'https://share.google/489YxsQigXiiFQWzE';

        $text = "{$name}\n\n"
            ."Assalamu’alaikum Wr. Wb.\n\n"
            ."Dengan hormat,\n"
            ."Kami mengundang {$name} untuk hadir dalam acara Grand Opening XIWAY COFFEE ☕✨\n\n"
            ."📅 Minggu, 20 September 2026\n"
            ."🕚 Pukul 11.00 WIB – selesai\n"
            ."📍 XIWAY COFFEE, Cimahi\n"
            ."{$mapsUrl}\n\n"
            ."Kehadiran Anda akan menjadi suatu kehormatan dan kebahagiaan bagi kami.\n\n"
            ."Kami tunggu kehadirannya untuk bersama-sama merayakan awal perjalanan XIWAY COFFEE. 🤎\n\n"
            ."See You at XIWAY COFFEE!\n"
            ."Good Coffee, Great People.\n\n"
            ."Undangan digital:\n{$inviteUrl}";

        return 'https://wa.me/?text='.rawurlencode($text);
    }
}
