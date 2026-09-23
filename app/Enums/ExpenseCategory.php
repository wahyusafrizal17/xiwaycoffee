<?php

namespace App\Enums;

/**
 * Kategori BOP coffee shop.
 *
 * Slug lama dipertahankan agar data existing tetap valid:
 * - bahan = Bahan Baku (sebelumnya "Bahan")
 * - wifi = Internet & Telekomunikasi (sebelumnya "Wifi")
 * - iuran = Iuran (legacy; input baru pakai operasional)
 *
 * Referensi penggunaan (tidak ditampilkan di dropdown):
 * Bahan Baku: kopi, susu, syrup, gula, … | Sewa: bangunan |
 * Gaji: gaji, bonus, THR | Listrik: PLN | Air: PDAM | Gas: LPG |
 * Internet: WiFi, pulsa | Kebersihan | Packaging: cup, lid |
 * Marketing: ads | Maintenance: service mesin | Software: hosting |
 * Payment & Bank: MDR | Operasional: iuran, retribusi | Administrasi: ATK |
 * Transportasi | Peralatan | Lainnya
 */
enum ExpenseCategory: string
{
    case Ingredients = 'bahan';
    case Rent = 'sewa';
    case Salary = 'gaji';
    case Electricity = 'listrik';
    case Water = 'air';
    case Gas = 'gas';
    case Wifi = 'wifi';
    case Cleaning = 'kebersihan';
    case Packaging = 'packaging';
    case Marketing = 'marketing';
    case Maintenance = 'maintenance';
    case Software = 'software';
    case PaymentBank = 'payment_bank';
    case Operational = 'operasional';
    case Administration = 'administrasi';
    case Transport = 'transportasi';
    case Equipment = 'peralatan';
    case Dues = 'iuran';
    case Other = 'lain';

    public function label(): string
    {
        return match ($this) {
            self::Ingredients => 'Bahan Baku',
            self::Rent => 'Sewa',
            self::Salary => 'Gaji',
            self::Electricity => 'Listrik',
            self::Water => 'Air',
            self::Gas => 'Gas',
            self::Wifi => 'Internet & Telekomunikasi',
            self::Cleaning => 'Kebersihan',
            self::Packaging => 'Packaging',
            self::Marketing => 'Marketing',
            self::Maintenance => 'Maintenance',
            self::Software => 'Software & Subscription',
            self::PaymentBank => 'Payment & Bank',
            self::Operational => 'Operasional',
            self::Administration => 'Administrasi',
            self::Transport => 'Transportasi & Logistik',
            self::Equipment => 'Peralatan',
            self::Dues => 'Iuran',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Urutan dropdown Catat BOP (tanpa Iuran legacy).
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [
            self::Ingredients,
            self::Rent,
            self::Salary,
            self::Electricity,
            self::Water,
            self::Gas,
            self::Wifi,
            self::Cleaning,
            self::Packaging,
            self::Marketing,
            self::Maintenance,
            self::Software,
            self::PaymentBank,
            self::Operational,
            self::Administration,
            self::Transport,
            self::Equipment,
            self::Other,
        ];
    }
}
