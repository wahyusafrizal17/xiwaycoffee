<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\MembershipLevel;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Enums\TableStatus;
use App\Enums\WasteReason;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Investor;
use App\Models\Outlet;
use App\Models\Printer;
use App\Models\Product;
use App\Models\Reward;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\StockOpnameService;
use App\Services\WasteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = $this->seedOutlets();
        $users = $this->seedUsers($outlets);
        $admin = $users['admin'];

        Auth::login($admin);

        $this->seedSettings();
        $this->seedInvestors();
        $this->attachCatalogToOutlets($outlets);
        $this->seedTables($outlets);
        $this->seedCustomers();
        $this->seedRewards();
        $this->seedInventories($outlets);
        $this->seedDiscounts($outlets);
        $this->seedPrinters($outlets['main']);

        $this->seedWastes($outlets['main']);
        $this->seedTransfers($outlets);
        $this->seedOpname($outlets['main']);
        $this->seedAuditLogs($admin);
    }

    /**
     * @return array{main: Outlet}
     */
    protected function seedOutlets(): array
    {
        $outlet = Outlet::query()->updateOrCreate(
            ['code' => 'CMH'],
            [
                'name' => 'Xiway Coffee Cimahi',
                'city' => 'Cimahi',
                'address' => 'Cimahi, Jawa Barat',
                'phone' => '',
                'latitude' => -6.8721000,
                'longitude' => 107.5425000,
                'geo_radius_m' => 150,
                'is_central_kitchen' => false,
                'is_active' => true,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
            ],
        );

        Outlet::query()->whereKeyNot($outlet->id)->update(['is_active' => false]);

        return ['main' => $outlet];
    }

    /**
     * @param  array<string, Outlet>  $outlets
     * @return array<string, User>
     */
    protected function seedUsers(array $outlets): array
    {
        $password = Hash::make('password');

        $definitions = [
            'admin' => [
                'name' => 'Wahyu',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'outlets' => array_values($outlets),
            ],
            'cashier' => [
                'name' => 'Dina Cashier',
                'email' => 'cashier@example.com',
                'role' => 'cashier',
                'outlets' => [$outlets['main']],
            ],
        ];

        $users = [];
        foreach ($definitions as $key => $def) {
            $user = User::query()->updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $role = Role::query()->where('name', $def['role'])->firstOrFail();
            $user->roles()->sync([$role->id]);

            $sync = [];
            foreach ($def['outlets'] as $index => $outlet) {
                $sync[$outlet->id] = ['is_default' => $index === 0];
            }
            $user->outlets()->sync($sync);

            $users[$key] = $user;
        }

        return $users;
    }

    protected function seedInvestors(): void
    {
        foreach (config('pos.partners', []) as $partner) {
            Investor::query()->updateOrCreate(
                ['name' => $partner['name']],
                [
                    'capital' => $partner['capital'],
                    'is_active' => true,
                ],
            );
        }
    }

    protected function seedSettings(): void
    {
        $settings = [
            'tax_rate' => '10',
            'service_charge' => '0',
            'points_earn_per_amount' => '10000',
            'points_redeem_value' => '100',
            'company_name' => 'Xiway Coffee',
            'receipt_footer' => 'Terima kasih',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key, 'outlet_id' => null],
                ['value' => $value, 'group' => 'general'],
            );
        }
    }

    /**
     * @param  array<string, Outlet>  $outlets
     */
    protected function attachCatalogToOutlets(array $outlets): void
    {
        $products = Product::query()->get();

        foreach ($outlets as $outlet) {
            $sync = [];
            foreach ($products as $product) {
                $sync[$product->id] = [
                    'price' => $product->price,
                    'is_available' => true,
                ];
            }
            $outlet->products()->sync($sync);
        }
    }

    /**
     * @param  array<string, Outlet>  $outlets
     * @return array<string, Collection<int, DiningTable>>
     */
    protected function seedTables(array $outlets): array
    {
        $plans = [
            'main' => ['prefix' => 'T', 'from' => 1, 'to' => 10, 'occupied' => [1, 2]],
        ];
        $capacities = [2, 4, 6];
        $tables = [];

        foreach ($plans as $key => $plan) {
            $collection = collect();
            for ($n = $plan['from']; $n <= $plan['to']; $n++) {
                $index = $n - $plan['from'];
                $code = sprintf('%s-%02d', $plan['prefix'], $n);
                $occupied = in_array($n, $plan['occupied'], true);
                $table = DiningTable::query()->updateOrCreate(
                    ['outlet_id' => $outlets[$key]->id, 'code' => $code],
                    [
                        'name' => 'Meja '.$code,
                        'capacity' => $capacities[$index % 3],
                        'status' => $occupied ? TableStatus::Occupied : TableStatus::Available,
                        'pos_x' => 40 + ($index % 5) * 140,
                        'pos_y' => 40 + intdiv($index, 5) * 120,
                        'shape' => $index % 2 === 0 ? 'square' : 'round',
                        'zone' => $index < 5 ? 'Indoor' : 'Patio',
                        'is_active' => true,
                    ],
                );
                $collection->push($table);
            }
            $tables[$key] = $collection;
        }

        return $tables;
    }

    /**
     * @return Collection<int, Customer>
     */
    protected function seedCustomers()
    {
        $rows = [
            ['code' => 'CUS-001', 'name' => 'Andi Wijaya', 'phone' => '081234567001', 'email' => 'andi@example.com', 'birthday' => '1990-03-12', 'gender' => 'male', 'membership_level' => MembershipLevel::Gold, 'points' => 420, 'total_transaction' => 6200000],
            ['code' => 'CUS-002', 'name' => 'Siti Rahma', 'phone' => '081234567002', 'email' => 'siti@example.com', 'birthday' => '1994-07-21', 'gender' => 'female', 'membership_level' => MembershipLevel::Silver, 'points' => 180, 'total_transaction' => 1850000],
            ['code' => 'CUS-003', 'name' => 'Budi Santoso', 'phone' => '081234567003', 'email' => 'budi@example.com', 'birthday' => '1988-11-02', 'gender' => 'male', 'membership_level' => MembershipLevel::Regular, 'points' => 40, 'total_transaction' => 320000],
            ['code' => 'CUS-004', 'name' => 'Maya Putri', 'phone' => '081234567004', 'email' => 'maya@example.com', 'birthday' => '1996-01-18', 'gender' => 'female', 'membership_level' => MembershipLevel::Gold, 'points' => 510, 'total_transaction' => 7800000],
            ['code' => 'CUS-005', 'name' => 'Rudi Hartono', 'phone' => '081234567005', 'email' => 'rudi@example.com', 'birthday' => '1992-09-30', 'gender' => 'male', 'membership_level' => MembershipLevel::Silver, 'points' => 150, 'total_transaction' => 1420000],
            ['code' => 'CUS-006', 'name' => 'Dewi Lestari', 'phone' => '081234567006', 'email' => 'dewi@example.com', 'birthday' => '1998-05-08', 'gender' => 'female', 'membership_level' => MembershipLevel::Regular, 'points' => 20, 'total_transaction' => 185000],
            ['code' => 'CUS-007', 'name' => 'Farhan Malik', 'phone' => '081234567007', 'email' => 'farhan@example.com', 'birthday' => '1985-12-25', 'gender' => 'male', 'membership_level' => MembershipLevel::Gold, 'points' => 260, 'total_transaction' => 5400000],
            ['code' => 'CUS-008', 'name' => 'Nina Kusuma', 'phone' => '081234567008', 'email' => 'nina@example.com', 'birthday' => '1993-04-14', 'gender' => 'female', 'membership_level' => MembershipLevel::Silver, 'points' => 95, 'total_transaction' => 1100000],
            ['code' => 'CUS-009', 'name' => 'Agus Pratama', 'phone' => '081234567009', 'email' => 'agus@example.com', 'birthday' => '1991-08-03', 'gender' => 'male', 'membership_level' => MembershipLevel::Regular, 'points' => 10, 'total_transaction' => 95000],
        ];

        return collect($rows)->map(function (array $row) {
            return Customer::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + [
                    'address' => 'Indonesia',
                    'is_active' => true,
                    'last_transaction_at' => now()->subDays(random_int(1, 10)),
                ],
            );
        });
    }

    protected function seedRewards(): void
    {
        $rewards = [
            ['name' => 'Free Lemon Tea', 'description' => 'Gratis Lemon Tea', 'points_required' => 80, 'value' => 15000],
            ['name' => 'Diskon 20K', 'description' => 'Potongan Rp 20.000', 'points_required' => 150, 'value' => 20000],
            ['name' => 'Free Sanger', 'description' => 'Gratis Sanger Classic', 'points_required' => 200, 'value' => 18000],
        ];

        foreach ($rewards as $reward) {
            Reward::query()->updateOrCreate(
                ['name' => $reward['name']],
                $reward + ['is_active' => true],
            );
        }
    }

    /**
     * @param  array<string, Outlet>  $outlets
     */
    protected function seedInventories(array $outlets): void
    {
        $products = Product::query()->where('is_stockable', true)->get();

        foreach ($products as $product) {
            foreach ($outlets as $outlet) {
                if ($outlet->is_central_kitchen) {
                    $qty = $product->type === ProductType::Raw
                        ? random_int(80, 200)
                        : random_int(40, 140);
                } elseif ($product->is_sellable) {
                    $qty = random_int(160, 220);
                } else {
                    $qty = random_int(15, 80);
                }

                Inventory::query()->updateOrCreate(
                    ['outlet_id' => $outlet->id, 'product_id' => $product->id],
                    ['quantity' => $qty, 'reserved_quantity' => 0],
                );
            }
        }
    }

    /**
     * @param  array<string, Outlet>  $outlets
     */
    protected function seedDiscounts(array $outlets): void
    {
        $happyHour = Discount::query()->updateOrCreate(
            ['code' => 'HAPPYHOUR'],
            [
                'name' => 'Happy Hour',
                'type' => DiscountType::Nominal,
                'scope' => 'order',
                'value' => 10000,
                'minimum_transaction' => 0,
                'maximum_discount' => 10000,
                'start_time' => '15:00:00',
                'end_time' => '18:00:00',
                'is_active' => true,
            ],
        );

        $happyHour->outlets()->sync(collect($outlets)->pluck('id'));
    }

    protected function seedPrinters(Outlet $outlet): void
    {
        $makanan = Category::query()->where('name', 'Makanan')->first();
        $mie = Category::query()->where('name', 'Mie')->first();
        $snack = Category::query()->where('name', 'Snack')->first();
        $drinkNames = ['Coffee', 'Non Coffee', 'Fit Tea', 'Xiway Main'];

        $kitchen = Printer::query()->updateOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'Cimahi Kitchen'],
            ['station' => PrinterStation::Kitchen, 'ip_address' => '192.168.1.21', 'port' => 9100, 'is_active' => true],
        );
        $kitchen->routes()->delete();
        foreach ([$makanan, $mie, $snack] as $category) {
            if ($category) {
                $kitchen->routes()->create(['category_id' => $category->id, 'station' => PrinterStation::Kitchen->value]);
            }
        }

        $bar = Printer::query()->updateOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'Cimahi Bar'],
            ['station' => PrinterStation::Bar, 'ip_address' => '192.168.1.22', 'port' => 9100, 'is_active' => true],
        );
        $bar->routes()->delete();
        foreach ($drinkNames as $name) {
            $category = Category::query()->where('name', $name)->first();
            if ($category) {
                $bar->routes()->create(['category_id' => $category->id, 'station' => PrinterStation::Bar->value]);
            }
        }

        Printer::query()->updateOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'Cimahi Cashier'],
            ['station' => PrinterStation::Cashier, 'ip_address' => '192.168.1.20', 'port' => 9100, 'is_active' => true],
        );
    }

    protected function seedWastes(Outlet $outlet): void
    {
        $waste = app(WasteService::class);
        $milk = Product::query()->where('name', 'Milk')->where('is_active', true)->first();
        $coffee = Product::query()->where('name', 'Coffee Bean')->where('is_active', true)->first();
        if (! $milk || ! $coffee) {
            return;
        }

        $waste->record([
            'outlet_id' => $outlet->id,
            'product_id' => $milk->id,
            'quantity' => 1,
            'reason' => WasteReason::Spoiled->value,
            'notes' => 'Susu basah',
        ]);

        $waste->record([
            'outlet_id' => $outlet->id,
            'product_id' => $coffee->id,
            'quantity' => 1,
            'reason' => WasteReason::Damaged->value,
            'notes' => 'Kemasan bocor',
        ]);
    }

    /**
     * @param  array<string, Outlet>  $outlets
     */
    protected function seedTransfers(array $outlets): void
    {
        // Single outlet — no inter-branch transfer demo data.
    }

    protected function seedOpname(Outlet $outlet): void
    {
        $opnames = app(StockOpnameService::class);
        $opname = $opnames->create([
            'outlet_id' => $outlet->id,
            'notes' => 'Opname harian Cimahi',
        ]);

        $flour = Product::query()->where('name', 'Sugar')->where('is_active', true)->first();
        $item = $opname->items->firstWhere('product_id', $flour?->id) ?? $opname->items->first();
        if ($item) {
            $opnames->updateItems($opname, [[
                'id' => $item->id,
                'physical_qty' => (float) $item->system_qty + 2,
                'reason' => 'Kelebihan stok fisik',
            ]]);
        }

        $opnames->finalize($opname->fresh(['items']));
    }

    protected function seedAuditLogs(User $admin): void
    {
        $entries = [
            ['action' => 'login', 'module' => 'auth', 'new' => ['email' => $admin->email]],
            ['action' => 'updated', 'module' => 'settings', 'new' => ['tax_rate' => 10]],
            ['action' => 'viewed', 'module' => 'reports', 'new' => ['report' => 'sales']],
        ];

        foreach ($entries as $entry) {
            AuditLog::query()->create([
                'user_id' => $admin->id,
                'action' => $entry['action'],
                'module' => $entry['module'],
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => $entry['new'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Rasa Seeder',
            ]);
        }
    }
}
