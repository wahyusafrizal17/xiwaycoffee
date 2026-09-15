<?php

namespace Tests\Support;

use App\Enums\DiscountType;
use App\Enums\MembershipLevel;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Enums\TableStatus;
use App\Models\Bom;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

trait SeedsPosFixture
{
    protected Outlet $outlet;

    protected Outlet $centralKitchen;

    protected User $admin;

    protected User $cashier;

    protected Unit $unitPcs;

    protected Unit $unitKg;

    protected Category $foodCategory;

    protected Product $sellableProduct;

    protected Product $rawChicken;

    protected Product $rawSeasoning;

    protected Product $marinatedChicken;

    protected Bom $bom;

    protected Customer $customer;

    protected Discount $weekdayPromo;

    protected DiningTable $tableA;

    protected DiningTable $tableB;

    protected Inventory $sellableInventory;

    protected function seedPosFixture(): void
    {
        $this->seed(RoleSeeder::class);

        $this->outlet = Outlet::query()->create([
            'code' => 'BDG',
            'name' => 'Outlet Bandung',
            'city' => 'Bandung',
            'is_central_kitchen' => false,
            'is_active' => true,
        ]);

        $this->centralKitchen = Outlet::query()->create([
            'code' => 'CK',
            'name' => 'Central Kitchen Jakarta',
            'city' => 'Jakarta',
            'is_central_kitchen' => true,
            'is_active' => true,
        ]);

        $this->unitPcs = Unit::query()->create(['code' => 'PCS', 'name' => 'Pieces', 'family' => 'count', 'conversion_factor' => 1]);
        $this->unitKg = Unit::query()->create(['code' => 'KG', 'name' => 'Kilogram', 'family' => 'weight', 'conversion_factor' => 1000]);

        $this->foodCategory = Category::query()->create([
            'name' => 'Food',
            'slug' => 'food',
            'station' => PrinterStation::Kitchen->value,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->admin = $this->makeUser('Admin User', 'admin@example.com', 'admin', [$this->outlet, $this->centralKitchen]);
        $this->cashier = $this->makeUser('Cashier User', 'cashier@example.com', 'cashier', [$this->outlet]);

        $this->sellableProduct = Product::query()->create([
            'sku' => 'PRD-TEST-001',
            'name' => 'Chicken Burger',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 35000,
            'cost' => 15000,
            'is_sellable' => true,
            'is_stockable' => true,
            'is_active' => true,
            'minimum_stock' => 5,
            'reorder_level' => 10,
            'maximum_stock' => 100,
            'station' => PrinterStation::Kitchen->value,
            'prep_minutes' => 10,
        ]);

        $this->rawChicken = Product::query()->create([
            'sku' => 'PRD-TEST-CHICKEN',
            'name' => 'Chicken',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitKg->id,
            'type' => ProductType::Raw,
            'bom_level' => 0,
            'price' => 0,
            'is_sellable' => false,
            'is_stockable' => true,
            'is_active' => true,
            'reorder_level' => 10,
            'station' => PrinterStation::Kitchen->value,
        ]);

        $this->rawSeasoning = Product::query()->create([
            'sku' => 'PRD-TEST-SEASONING',
            'name' => 'Seasoning',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitKg->id,
            'type' => ProductType::Raw,
            'bom_level' => 0,
            'price' => 0,
            'is_sellable' => false,
            'is_stockable' => true,
            'is_active' => true,
            'reorder_level' => 2,
            'station' => PrinterStation::Kitchen->value,
        ]);

        $this->marinatedChicken = Product::query()->create([
            'sku' => 'PRD-TEST-MARINADE',
            'name' => 'Marinated Chicken',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitKg->id,
            'type' => ProductType::SemiFinished,
            'bom_level' => 1,
            'price' => 0,
            'is_sellable' => false,
            'is_stockable' => true,
            'is_active' => true,
            'reorder_level' => 5,
            'station' => PrinterStation::Kitchen->value,
        ]);

        $this->bom = Bom::query()->create([
            'product_id' => $this->marinatedChicken->id,
            'version' => '1.0',
            'yield_percentage' => 100,
            'waste_percentage' => 0,
            'is_active' => true,
        ]);
        $this->bom->items()->create([
            'component_id' => $this->rawChicken->id,
            'unit_id' => $this->unitKg->id,
            'quantity' => 1,
            'waste_percentage' => 0,
            'yield_percentage' => 100,
        ]);
        $this->bom->items()->create([
            'component_id' => $this->rawSeasoning->id,
            'unit_id' => $this->unitKg->id,
            'quantity' => 0.05,
            'waste_percentage' => 0,
            'yield_percentage' => 100,
        ]);

        $this->sellableInventory = Inventory::query()->create([
            'outlet_id' => $this->outlet->id,
            'product_id' => $this->sellableProduct->id,
            'quantity' => 20,
        ]);

        foreach ([$this->outlet, $this->centralKitchen] as $outlet) {
            Inventory::query()->firstOrCreate(
                ['outlet_id' => $outlet->id, 'product_id' => $this->rawChicken->id],
                ['quantity' => 50],
            );
            Inventory::query()->firstOrCreate(
                ['outlet_id' => $outlet->id, 'product_id' => $this->rawSeasoning->id],
                ['quantity' => 20],
            );
            Inventory::query()->firstOrCreate(
                ['outlet_id' => $outlet->id, 'product_id' => $this->marinatedChicken->id],
                ['quantity' => 2],
            );
        }

        $this->customer = Customer::query()->create([
            'code' => 'CUS-TEST-001',
            'name' => 'Andi Wijaya',
            'phone' => '081234567001',
            'email' => 'andi@example.com',
            'membership_level' => MembershipLevel::Regular,
            'points' => 0,
            'total_transaction' => 0,
            'is_active' => true,
        ]);

        $this->weekdayPromo = Discount::query()->create([
            'name' => 'Promo Weekday 10%',
            'code' => 'WEEKDAY10',
            'type' => DiscountType::Percentage,
            'scope' => 'order',
            'value' => 10,
            'minimum_transaction' => 50000,
            'maximum_discount' => 20000,
            'is_active' => true,
        ]);

        $this->tableA = DiningTable::query()->create([
            'outlet_id' => $this->outlet->id,
            'code' => 'T-01',
            'name' => 'Meja T-01',
            'capacity' => 4,
            'status' => TableStatus::Available,
            'pos_x' => 40,
            'pos_y' => 40,
            'is_active' => true,
        ]);

        $this->tableB = DiningTable::query()->create([
            'outlet_id' => $this->outlet->id,
            'code' => 'T-02',
            'name' => 'Meja T-02',
            'capacity' => 4,
            'status' => TableStatus::Available,
            'pos_x' => 180,
            'pos_y' => 40,
            'is_active' => true,
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'tax_rate', 'outlet_id' => null],
            ['value' => '11', 'group' => 'general'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'points_earn_per_amount', 'outlet_id' => null],
            ['value' => '10000', 'group' => 'general'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'points_redeem_value', 'outlet_id' => null],
            ['value' => '100', 'group' => 'general'],
        );
    }

    /**
     * @param  list<Outlet>  $outlets
     */
    protected function makeUser(string $name, string $email, string $roleName, array $outlets): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        $sync = [];
        foreach ($outlets as $index => $outlet) {
            $sync[$outlet->id] = ['is_default' => $index === 0];
        }
        $user->outlets()->sync($sync);

        return $user->fresh(['roles', 'outlets']);
    }

    protected function actingAsAtOutlet(User $user, ?Outlet $outlet = null): static
    {
        $outlet ??= $this->outlet;

        return $this->actingAs($user)->withSession(['current_outlet_id' => $outlet->id]);
    }
}
