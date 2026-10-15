<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['employee_id', 'outlet_id', 'work_date', 'url'])]
class ContentLink extends Model
{
    use AppliesFillableAttribute;

    public const DAILY = 3;

    public const FINE = 10_000;

    public const START = '2026-10-16';

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($host !== 'instagram.com' || $path === '') {
            return null;
        }

        return 'https://instagram.com'.$path;
    }

    public static function share(int $outletId, string $from, string $to, int $heads): int
    {
        $start = max($from, self::START);
        $end = min($to, now('Asia/Jakarta')->subDay()->toDateString());
        if ($heads < 1 || $start > $end) {
            return 0;
        }

        $counts = static::query()
            ->where('outlet_id', $outletId)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->get()
            ->groupBy(fn (self $link) => $link->work_date->toDateString())
            ->map->count();
        $missed = 0;
        for ($day = Carbon::parse($start); $day->toDateString() <= $end; $day->addDay()) {
            $missed += max(0, self::DAILY - (int) ($counts[$day->toDateString()] ?? 0));
        }

        return intdiv($missed * self::FINE, $heads);
    }
}
