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
            '2026-09-30' => 'aobc',
            '2026-10-01' => 'abcb',
            '2026-10-02' => 'acbc',
            '2026-10-03' => 'abco',
            '2026-10-04' => 'abcc',
            '2026-10-05' => 'aboc',
            '2026-10-06' => 'acca',
            '2026-10-07' => 'abco',
            '2026-10-08' => 'acbc',
            '2026-10-09' => 'aoac',
            '2026-10-10' => 'abcb',
            '2026-10-11' => 'acbc',
            '2026-10-12' => 'accb',
            '2026-10-13' => 'abca',
            '2026-10-14' => 'acob',
            '2026-10-15' => 'accb',
            '2026-10-16' => 'abac',
            '2026-10-17' => 'abco',
            '2026-10-18' => 'aocb',
            '2026-10-19' => 'abcc',
            '2026-10-20' => 'acbc',
            '2026-10-21' => 'accb',
            '2026-10-22' => 'abca',
            '2026-10-23' => 'aboc',
            '2026-10-24' => 'accb',
            '2026-10-25' => 'aboc',
            '2026-10-26' => 'acbc',
            '2026-10-27' => 'aobc',
            '2026-10-28' => 'abcc',
            '2026-10-29' => 'abco',
            '2026-10-30' => 'accb',
            '2026-10-31' => 'aobc',
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
