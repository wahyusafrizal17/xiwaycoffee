<?php

namespace App\Services;

use Illuminate\Support\Str;

class NumberGenerator
{
    public function next(string $prefix, string $table, string $column = 'number'): string
    {
        $date = now()->format('Ymd');
        $like = "{$prefix}-{$date}-%";

        $latest = \Illuminate\Support\Facades\DB::table($table)
            ->where($column, 'like', $like)
            ->orderByDesc($column)
            ->value($column);

        $seq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }

    public function unique(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
    }
}
