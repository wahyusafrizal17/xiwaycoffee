<?php

namespace App\Imports;

use App\Enums\MembershipLevel;
use App\Models\Customer;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerImport implements SkipsEmptyRows, ToModel, WithHeadingRow
{
    public function model(array $row): ?Customer
    {
        $name = $row['nama'] ?? $row['nama_pelanggan'] ?? $row['name'] ?? null;
        if (! filled($name)) {
            return null;
        }

        $level = strtolower((string) ($row['level'] ?? $row['membership'] ?? 'regular'));
        $level = MembershipLevel::tryFrom($level)?->value ?? MembershipLevel::Regular->value;
        $code = filled($row['kode'] ?? null)
            ? (string) $row['kode']
            : 'CUS-'.strtoupper(Str::random(6));

        Customer::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'phone' => $row['no_hp'] ?? $row['nohp'] ?? $row['telepon'] ?? $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'address' => $row['alamat'] ?? $row['address'] ?? null,
                'membership_level' => $level,
                'is_active' => true,
            ],
        );

        return null;
    }
}
