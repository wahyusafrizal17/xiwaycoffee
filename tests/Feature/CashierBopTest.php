<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\ExpensePaymentMethod;
use App\Models\OperatingExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class CashierBopTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_cashier_can_view_and_store_bop(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.expenses'))
            ->assertOk()
            ->assertSee('Bahan Baku')
            ->assertSee('Internet & Telekomunikasi')
            ->assertSee('Maintenance');

        $this->actingAs($this->cashier)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => ExpenseCategory::Ingredients->value,
                'amount' => 50000,
                'payment_method' => ExpensePaymentMethod::Cash->value,
                'notes' => 'Belanja es batu',
            ])
            ->assertRedirect(route('reports.expenses'));

        $this->assertDatabaseHas('operating_expenses', [
            'amount' => 50000,
            'category' => 'bahan',
            'payment_method' => 'cash',
            'notes' => 'Belanja es batu',
            'user_id' => $this->cashier->id,
        ]);
    }

    public function test_legacy_bahan_and_wifi_labels_still_display(): void
    {
        OperatingExpense::query()->create([
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->admin->id,
            'spent_on' => now()->toDateString(),
            'category' => 'bahan',
            'amount' => 100000,
            'notes' => 'Legacy bahan',
        ]);
        OperatingExpense::query()->create([
            'outlet_id' => $this->outlet->id,
            'user_id' => $this->admin->id,
            'spent_on' => now()->toDateString(),
            'category' => 'wifi',
            'amount' => 325000,
            'notes' => 'Legacy wifi',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.expenses'))
            ->assertOk()
            ->assertSee('Bahan Baku')
            ->assertSee('Internet & Telekomunikasi')
            ->assertSee('Legacy bahan')
            ->assertSee('Legacy wifi');
    }

    public function test_new_categories_and_filter_work(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => ExpenseCategory::Electricity->value,
                'amount' => 1000000,
                'notes' => 'Tagihan listrik September 2026',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => ExpenseCategory::Wifi->value,
                'amount' => 325000,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => ExpenseCategory::Maintenance->value,
                'amount' => 500000,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('reports.expenses', ['category' => 'maintenance', 'q' => '']))
            ->assertOk()
            ->assertSee('Maintenance')
            ->assertSee(money(500000));

        $this->assertEquals(3, OperatingExpense::query()->count());
        $this->assertEquals(ExpenseCategory::Ingredients->label(), 'Bahan Baku');
        $this->assertEquals(ExpenseCategory::Wifi->label(), 'Internet & Telekomunikasi');
    }

    public function test_cashier_cannot_open_other_reports(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.sales'))
            ->assertForbidden();
    }
}
