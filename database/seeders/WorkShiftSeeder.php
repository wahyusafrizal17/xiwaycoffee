<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class WorkShiftSeeder extends Seeder
{
    public function run(): void
    {
        $emails = [
            'zakki@xiway.local',
            'ergina@xiway.local',
            'naurah@xiway.local',
            'jimmy@xiway.local',
        ];

        $employees = [];
        foreach ($emails as $email) {
            $employee = User::query()->where('email', $email)->first()?->employee;
            if (! $employee) {
                return;
            }
            $employees[] = $employee;
        }

        $slots = [
            'a' => ['09:00', '22:00'],
            'b' => ['09:00', '17:00'],
            'c' => ['14:00', '22:00'],
            'o' => [null, null],
        ];

        $rows = [
            '2026-09-30' => 'cbbc',
            '2026-10-01' => 'cobc',
            '2026-10-02' => 'ccbo',
            '2026-10-03' => 'bccb',
            '2026-10-04' => 'bbcc',
            '2026-10-05' => 'bbcc',
            '2026-10-06' => 'ccob',
            '2026-10-07' => 'bbcc',
            '2026-10-08' => 'cbbc',
            '2026-10-09' => 'cobc',
            '2026-10-10' => 'bbcc',
            '2026-10-11' => 'ccbb',
            '2026-10-12' => 'ccbo',
            '2026-10-13' => 'bbcc',
            '2026-10-14' => 'bccb',
            '2026-10-15' => 'obac',
            '2026-10-16' => 'bbcc',
            '2026-10-17' => 'ccbb',
            '2026-10-18' => 'cbbc',
            '2026-10-19' => 'aboc',
            '2026-10-20' => 'baco',
            '2026-10-21' => 'ocab',
            '2026-10-22' => 'acob',
            '2026-10-23' => 'boca',
            '2026-10-24' => 'ccbb',
            '2026-10-25' => 'bccb',
            '2026-10-26' => 'obac',
            '2026-10-27' => 'boca',
            '2026-10-28' => 'aboc',
            '2026-10-29' => 'ocab',
            '2026-10-30' => 'cabo',
            '2026-10-31' => 'cbbc',
        ];

        foreach ($rows as $date => $code) {
            foreach ($employees as $index => $employee) {
                [$start, $end] = $slots[$code[$index]];
                $employee->shifts()->updateOrCreate(
                    ['work_date' => $date],
                    ['starts_at' => $start, 'ends_at' => $end],
                );
            }
        }
    }
}
