<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $titles = [
            'Zakki' => ['Barista / Senior Crew', 'Bar'],
            'Naurah' => ['Café Crew', 'Bar Support'],
            'Ergina' => ['Café Crew', 'Cashier'],
            'Jimmy' => ['Café Crew', 'Service/Floor'],
        ];
        $hasPrimary = Schema::hasColumn('employees', 'primary_position');

        foreach ($titles as $name => [$position, $primary]) {
            $employee = Employee::query()->whereHas('user', fn ($query) => $query->where('name', $name))->first();
            if (! $employee) {
                continue;
            }

            $data = ['position' => $position];
            if ($hasPrimary) {
                $data['primary_position'] = $primary;
            }
            $employee->update($data);
        }
    }

    public function down(): void
    {
        $titles = [
            'Zakki' => 'Barista',
            'Naurah' => 'Assisten Barista',
            'Ergina' => 'Kasir',
            'Jimmy' => 'Waiters',
        ];

        foreach ($titles as $name => $position) {
            Employee::query()->whereHas('user', fn ($query) => $query->where('name', $name))->update([
                'position' => $position,
                'primary_position' => '',
            ]);
        }
    }
};
