<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
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
            ->assertOk();

        $this->actingAs($this->cashier)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => ExpenseCategory::Ingredients->value,
                'amount' => 50000,
                'notes' => 'Belanja es batu',
            ])
            ->assertRedirect(route('reports.expenses'));

        $this->assertDatabaseHas('operating_expenses', [
            'amount' => 50000,
            'notes' => 'Belanja es batu',
            'user_id' => $this->cashier->id,
        ]);
    }

    public function test_cashier_cannot_open_other_reports(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.sales'))
            ->assertForbidden();
    }
}
