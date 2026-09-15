<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\MembershipLevel;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Enums\TableStatus;
use App\Enums\WasteReason;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\DiningTable;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Product;
use App\Models\Reward;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TableReservation;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use App\Services\WasteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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
        $this->attachCatalogToOutlets($outlets);
        $tables = $this->seedTables($outlets);
        $customers = $this->seedCustomers();
        $this->seedRewards();
        $this->seedInventories($outlets);
        $this->seedBundle($outlets);
        $this->seedDiscounts($outlets);
        $this->seedPrinters($outlets['bdg']);

        $this->seedPaidOrders($outlets, $users, $tables, $customers);
        $this->seedOpenOrders($outlets, $users, $tables, $customers);
        $this->seedReservation($outlets['bdg'], $tables['bdg']->firstWhere('code', 'T-10'), $customers->first());
        $this->seedWastes($outlets['bdg']);
        $this->seedTransfers($outlets);
        $this->seedOpname($outlets['bdg']);
        $this->seedAuditLogs($admin);
    }

    /**
     * @return array{bdg: Outlet, jkt: Outlet}
     */
    protected function seedOutlets(): array
    {
        $rows = [
            'bdg' => ['code' => 'BDG', 'name' => 'Outlet Bandung', 'city' => 'Bandung', 'address' => 'Jl. Dago No. 12', 'phone' => '022-555-1001', 'is_central_kitchen' => false],
            'jkt' => ['code' => 'JKT', 'name' => 'Outlet Jakarta', 'city' => 'Jakarta', 'address' => 'Jl. Senopati No. 8', 'phone' => '021-555-2002', 'is_central_kitchen' => false],
        ];

        $outlets = [];
        foreach ($rows as $key => $row) {
            $outlets[$key] = Outlet::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00'],
            );
        }

        return $outlets;
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
                'outlets' => [$outlets['bdg']],
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

    protected function seedSettings(): void
    {
        $settings = [
            'tax_rate' => '11',
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
     * @return array<string, \Illuminate\Support\Collection<int, DiningTable>>
     */
    protected function seedTables(array $outlets): array
    {
        $plans = [
            'bdg' => ['prefix' => 'T', 'from' => 1, 'to' => 10, 'occupied' => [1, 2]],
            'jkt' => ['prefix' => 'T', 'from' => 11, 'to' => 15, 'occupied' => []],
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
     * @return \Illuminate\Support\Collection<int, Customer>
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
    protected function seedBundle(array $outlets): void
    {
        Bundle::query()->where('sku', 'BND-HEMAT-A')->update(['is_active' => false]);
    }

    /**
     * @param  array<string, Outlet>  $outlets
     */
    protected function seedDiscounts(array $outlets): void
    {
        $weekday = Discount::query()->updateOrCreate(
            ['code' => 'WEEKDAY10'],
            [
                'name' => 'Promo Weekday 10%',
                'type' => DiscountType::Percentage,
                'scope' => 'order',
                'value' => 10,
                'minimum_transaction' => 50000,
                'maximum_discount' => 20000,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ],
        );

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

        $ids = collect($outlets)->pluck('id');
        $weekday->outlets()->sync($ids);
        $happyHour->outlets()->sync($ids);
    }

    protected function seedPrinters(Outlet $bandung): void
    {
        $makanan = Category::query()->where('name', 'Makanan')->first();
        $mie = Category::query()->where('name', 'Mie')->first();
        $snack = Category::query()->where('name', 'Snack')->first();
        $drinkNames = ['Coffee', 'Non Coffee', 'Fit Tea', 'Xiway Main'];

        $kitchen = Printer::query()->updateOrCreate(
            ['outlet_id' => $bandung->id, 'name' => 'Bandung Kitchen'],
            ['station' => PrinterStation::Kitchen, 'ip_address' => '192.168.1.21', 'port' => 9100, 'is_active' => true],
        );
        $kitchen->routes()->delete();
        foreach ([$makanan, $mie, $snack] as $category) {
            if ($category) {
                $kitchen->routes()->create(['category_id' => $category->id, 'station' => PrinterStation::Kitchen->value]);
            }
        }

        $bar = Printer::query()->updateOrCreate(
            ['outlet_id' => $bandung->id, 'name' => 'Bandung Bar'],
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
            ['outlet_id' => $bandung->id, 'name' => 'Bandung Cashier'],
            ['station' => PrinterStation::Cashier, 'ip_address' => '192.168.1.20', 'port' => 9100, 'is_active' => true],
        );
    }

    /**
     * @param  array<string, Outlet>  $outlets
     * @param  array<string, User>  $users
     * @param  array<string, \Illuminate\Support\Collection<int, DiningTable>>  $tables
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     */
    protected function seedPaidOrders(array $outlets, array $users, array $tables, $customers): void
    {
        $sellables = Product::query()
            ->with(['variants', 'category'])
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->where('type', '!=', ProductType::Package)
            ->orderBy('id')
            ->get();

        $saleOutlets = [$outlets['bdg'], $outlets['jkt']];
        $cashiers = [$users['admin'], $users['cashier']];
        $methods = [PaymentMethod::Cash, PaymentMethod::Card, PaymentMethod::Qris];
        $types = [OrderType::DineIn, OrderType::Pickup, OrderType::Online];
        $channels = [OrderChannel::Pos, OrderChannel::Pickup, OrderChannel::Online];

        $index = 0;
        $sequences = [];

        for ($day = 13; $day >= 0; $day--) {
            $perDay = $day === 0 ? 8 : 3 + ($day % 2);
            for ($n = 0; $n < $perDay; $n++) {
                $when = Carbon::today()->subDays($day)->setTime(10 + ($n * 1), 15 + ($n * 7));
                $outlet = $saleOutlets[$index % count($saleOutlets)];
                $user = $cashiers[$index % count($cashiers)];
                $type = $types[$index % count($types)];
                $channel = $type === OrderType::DineIn ? OrderChannel::Pos : $channels[$index % count($channels)];
                $method = $methods[$index % count($methods)];
                $customer = $index % 3 === 0 ? null : $customers[$index % $customers->count()];
                $table = null;
                if ($type === OrderType::DineIn) {
                    $outletTables = match ($outlet->code) {
                        'BDG' => $tables['bdg'],
                        default => $tables['jkt'],
                    };
                    $table = $outletTables[$index % $outletTables->count()];
                }

                $dateKey = $when->format('Ymd');
                $sequences[$dateKey] = ($sequences[$dateKey] ?? 0) + 1;
                $orderNumber = sprintf('ORD-%s-%04d', $dateKey, $sequences[$dateKey]);

                $lineCount = 1 + ($index % 4);
                $lines = [];
                $subtotal = 0;
                for ($i = 0; $i < $lineCount; $i++) {
                    $product = $sellables[($index + $i) % $sellables->count()];
                    $variant = $product->variants->isNotEmpty()
                        ? $product->variants[$i % $product->variants->count()]
                        : null;
                    $qty = 1 + (($index + $i) % 2);
                    $price = (float) $product->price + (float) ($variant?->price_adjustment ?? 0);
                    $lines[] = compact('product', 'variant', 'qty', 'price');
                    $subtotal += $price * $qty;
                }

                $tax = round($subtotal * 0.11, 2);
                $grand = $subtotal + $tax;
                $tendered = $method === PaymentMethod::Cash ? ceil($grand / 10000) * 10000 : $grand;

                $order = new Order([
                    'order_number' => $orderNumber,
                    'outlet_id' => $outlet->id,
                    'user_id' => $user->id,
                    'customer_id' => $customer?->id,
                    'table_id' => $type === OrderType::DineIn ? $table?->id : null,
                    'channel' => $channel,
                    'order_type' => $type,
                    'status' => OrderStatus::Completed,
                    'payment_status' => PaymentStatus::Paid,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_amount' => $tax,
                    'tax_rate' => 11,
                    'service_charge' => 0,
                    'grand_total' => $grand,
                    'guest_count' => 1 + ($index % 4),
                    'completed_at' => $when->copy()->addMinutes(18),
                ]);
                $order->created_at = $when;
                $order->updated_at = $when->copy()->addMinutes(18);
                $order->save();

                foreach ($lines as $line) {
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $line['product']->id,
                        'product_variant_id' => $line['variant']?->id,
                        'name' => $line['variant']
                            ? $line['product']->name.' ('.$line['variant']->name.')'
                            : $line['product']->name,
                        'quantity' => $line['qty'],
                        'unit_price' => $line['price'],
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'total' => $line['price'] * $line['qty'],
                        'consignment_commission' => (float) ($line['product']->consignment_commission ?? 0),
                        'station' => $line['product']->station ?? $line['product']->category?->station,
                        'status' => 'completed',
                    ]);

                    if ($line['product']->is_stockable) {
                        $this->recordSaleMovement($outlet, $line['product'], (float) $line['qty'], $order, $when);
                    }
                }

                Payment::query()->create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'method' => $method,
                    'amount' => $grand,
                    'tendered' => $tendered,
                    'change_amount' => max(0, $tendered - $grand),
                    'reference' => $method === PaymentMethod::Cash ? null : strtoupper($method->value).'-'.$order->id,
                    'status' => 'paid',
                    'created_at' => $when->copy()->addMinutes(16),
                    'updated_at' => $when->copy()->addMinutes(16),
                ]);

                foreach ([
                    [null, OrderStatus::Draft->value, 'Order dibuat'],
                    [OrderStatus::Draft->value, OrderStatus::New->value, 'Order dikirim ke dapur'],
                    [OrderStatus::New->value, OrderStatus::Completed->value, 'Order selesai'],
                ] as $history) {
                    OrderStatusHistory::query()->create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'from_status' => $history[0],
                        'to_status' => $history[1],
                        'notes' => $history[2],
                        'created_at' => $when,
                        'updated_at' => $when,
                    ]);
                }

                if ($customer) {
                    $customer->increment('total_transaction', $grand);
                    $customer->update(['last_transaction_at' => $order->completed_at]);

                    $points = (int) floor($grand / 10000);
                    if ($points > 0) {
                        $balance = (int) $customer->points + $points;
                        $customer->update(['points' => $balance]);
                        CustomerPoint::query()->create([
                            'customer_id' => $customer->id,
                            'order_id' => $order->id,
                            'user_id' => $user->id,
                            'type' => 'earn',
                            'points' => $points,
                            'balance_after' => $balance,
                            'reason' => 'Pembelian '.$order->order_number,
                            'created_at' => $order->completed_at,
                            'updated_at' => $order->completed_at,
                        ]);
                    }
                    $customer->refreshMembership();
                }

                $index++;
            }
        }
    }

    protected function recordSaleMovement(Outlet $outlet, Product $product, float $qty, Order $order, Carbon $when): void
    {
        $inventory = Inventory::query()->firstOrCreate(
            ['outlet_id' => $outlet->id, 'product_id' => $product->id],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );

        $before = (float) $inventory->quantity;
        $after = max(0, $before - $qty);
        $inventory->update(['quantity' => $after]);

        $movement = InventoryMovement::query()->create([
            'reference_number' => $order->order_number,
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'user_id' => $order->user_id,
            'type' => StockMovementType::Sale,
            'quantity' => -$qty,
            'before_stock' => $before,
            'after_stock' => $after,
            'reason' => 'Penjualan '.$order->order_number,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);
        $movement->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
    }

    /**
     * @param  array<string, Outlet>  $outlets
     * @param  array<string, User>  $users
     * @param  array<string, \Illuminate\Support\Collection<int, DiningTable>>  $tables
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     */
    protected function seedOpenOrders(array $outlets, array $users, array $tables, $customers): void
    {
        $orders = app(OrderService::class);
        $sanger = Product::query()->where('name', 'Sanger Classic')->where('is_active', true)->firstOrFail();
        $pecak = Product::query()->where('name', 'Nasi Ayam Pecak')->where('is_active', true)->firstOrFail();
        $tea = Product::query()->where('name', 'Lemon Tea')->where('is_active', true)->firstOrFail();
        $mie = Product::query()->where('name', 'Mie Aceh Biasa')->where('is_active', true)->firstOrFail();

        $held = $orders->createDraft([
            'outlet_id' => $outlets['bdg']->id,
            'customer_id' => $customers[1]->id,
            'order_type' => OrderType::Pickup->value,
            'channel' => OrderChannel::Pos->value,
            'guest_count' => 1,
            'notes' => 'Held for later',
        ]);
        $orders->addItem($held, ['product_id' => $tea->id, 'quantity' => 2]);
        $orders->hold($held);

        $new = $orders->createDraft([
            'outlet_id' => $outlets['bdg']->id,
            'table_id' => $tables['bdg']->firstWhere('code', 'T-01')->id,
            'customer_id' => $customers[0]->id,
            'order_type' => OrderType::DineIn->value,
            'channel' => OrderChannel::Pos->value,
            'guest_count' => 2,
        ]);
        $orders->addItem($new, ['product_id' => $pecak->id, 'quantity' => 1]);
        $orders->addItem($new, ['product_id' => $sanger->id, 'quantity' => 1]);
        $orders->submit($new);

        $processing = $orders->createDraft([
            'outlet_id' => $outlets['bdg']->id,
            'table_id' => $tables['bdg']->firstWhere('code', 'T-02')->id,
            'order_type' => OrderType::DineIn->value,
            'channel' => OrderChannel::Pos->value,
            'guest_count' => 4,
        ]);
        $orders->addItem($processing, ['product_id' => $pecak->id, 'quantity' => 2]);
        $orders->submit($processing);
        $orders->transition($processing->fresh(), OrderStatus::Processing, 'Sedang dimasak');

        $ready = $orders->createDraft([
            'outlet_id' => $outlets['jkt']->id,
            'order_type' => OrderType::Pickup->value,
            'channel' => OrderChannel::Pickup->value,
            'customer_id' => $customers[3]->id,
            'guest_count' => 1,
        ]);
        $orders->addItem($ready, ['product_id' => $sanger->id, 'quantity' => 1]);
        $orders->submit($ready);
        $orders->transition($ready->fresh(), OrderStatus::Ready, 'Siap diambil');

        $online = $orders->createDraft([
            'outlet_id' => $outlets['jkt']->id,
            'order_type' => OrderType::Online->value,
            'channel' => OrderChannel::Online->value,
            'customer_id' => $customers[2]->id,
            'guest_count' => 1,
        ]);
        $orders->addItem($online, ['product_id' => $mie->id, 'quantity' => 1]);
        $orders->submit($online);
    }

    protected function seedReservation(Outlet $outlet, ?DiningTable $table, Customer $customer): void
    {
        if (! $table) {
            return;
        }

        TableReservation::query()->create([
            'outlet_id' => $outlet->id,
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'guest_name' => $customer->name,
            'guest_phone' => $customer->phone,
            'guest_count' => 4,
            'reserved_at' => now()->addHours(3),
            'status' => 'reserved',
            'notes' => 'Reservasi malam ini',
        ]);

        $table->update(['status' => TableStatus::Reserved]);
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
        $transfers = app(StockTransferService::class);
        $bean = Product::query()->where('name', 'Coffee Bean')->where('is_active', true)->first();
        $sugar = Product::query()->where('name', 'Sugar')->where('is_active', true)->first();
        if (! $bean || ! $sugar) {
            return;
        }

        $transfers->create([
            'source_outlet_id' => $outlets['jkt']->id,
            'destination_outlet_id' => $outlets['bdg']->id,
            'transfer_date' => now()->toDateString(),
            'notes' => 'Draft restock Bandung',
            'items' => [
                ['product_id' => $sugar->id, 'quantity' => 8],
            ],
        ]);

        $completed = $transfers->create([
            'source_outlet_id' => $outlets['jkt']->id,
            'destination_outlet_id' => $outlets['bdg']->id,
            'transfer_date' => now()->toDateString(),
            'notes' => 'Transfer biji kopi Jakarta ke Bandung',
            'items' => [
                ['product_id' => $bean->id, 'quantity' => 12],
            ],
        ]);
        $transfers->approve($completed);
        $transfers->ship($completed->fresh());
        $transfers->receive($completed->fresh());
    }

    protected function seedOpname(Outlet $outlet): void
    {
        $opnames = app(StockOpnameService::class);
        $opname = $opnames->create([
            'outlet_id' => $outlet->id,
            'notes' => 'Opname harian Bandung',
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
            ['action' => 'updated', 'module' => 'settings', 'new' => ['tax_rate' => 11]],
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
