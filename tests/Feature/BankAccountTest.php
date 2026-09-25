<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PrinterStation;
use App\Enums\ProductType;
use App\Models\Product;
use App\Services\BankAccountService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_qris_checkout_credits_rekening_cash_does_not(): void
    {
        $product = Product::query()->create([
            'sku' => 'PRD-BANK-1',
            'name' => 'Americano Bank',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 20000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Bar->value,
        ]);

        $orders = app(OrderService::class);
        $bank = app(BankAccountService::class);

        $this->actingAsAtOutlet($this->cashier);
        $cashOrder = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($cashOrder, ['product_id' => $product->id, 'quantity' => 1]);
        $orders->checkout($cashOrder->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 50000,
        ]);

        $this->assertEquals(0.0, $bank->balance($this->outlet->id));

        $qrisOrder = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($qrisOrder, ['product_id' => $product->id, 'quantity' => 1]);
        $qrisOrder = $orders->checkout($qrisOrder->fresh(), [
            'method' => PaymentMethod::Qris->value,
            'tendered' => 20000,
        ]);

        $this->assertEquals(
            (float) $qrisOrder->payments->sum('amount'),
            $bank->balance($this->outlet->id)
        );
    }

    public function test_cash_deposit_bop_and_food_settlement_move_rekening(): void
    {
        $food = Product::query()->create([
            'sku' => 'PRD-BANK-FOOD',
            'name' => 'Pecak Bank',
            'category_id' => $this->foodCategory->id,
            'unit_id' => $this->unitPcs->id,
            'type' => ProductType::Finished,
            'price' => 15000,
            'consignment_commission' => 2000,
            'is_sellable' => true,
            'is_stockable' => false,
            'is_active' => true,
            'station' => PrinterStation::Kitchen->value,
        ]);

        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $food->id, 'quantity' => 1]);
        $orders->checkout($order->fresh(), [
            'method' => PaymentMethod::Cash->value,
            'tendered' => 50000,
        ]);

        $this->actingAsAtOutlet($this->cashier)
            ->post(route('bank.deposits.store'), [
                'occurred_on' => now()->toDateString(),
                'amount' => 15000,
                'notes' => 'Setor cash ke BCA',
            ])
            ->assertRedirect();

        $bank = app(BankAccountService::class);
        $this->assertEquals(15000.0, $bank->balance($this->outlet->id));

        $this->actingAsAtOutlet($this->admin)
            ->post(route('reports.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'category' => 'sewa',
                'amount' => 5000,
                'notes' => 'Sewa dari rekening',
            ])
            ->assertRedirect();

        $this->assertEquals(10000.0, $bank->balance($this->outlet->id));

        $this->actingAsAtOutlet($this->admin)
            ->post(route('reports.setoran.store'), [
                'settled_on' => now()->toDateString(),
                'notes' => 'Setor mitra',
            ])
            ->assertRedirect();

        // food outstanding was 13000; after BOP balance 10000, settlement debits 13000 → -3000
        $this->assertEquals(-3000.0, $bank->balance($this->outlet->id));
    }

    public function test_admin_can_open_rekening_page(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('bank.index'))
            ->assertForbidden();

        $this->actingAsAtOutlet($this->admin)
            ->get(route('bank.index'))
            ->assertOk()
            ->assertSee('Rekening')
            ->assertSee('Saldo');
    }

    public function test_setoran_kas_page_lists_deposits_and_modal_form(): void
    {
        $this->actingAsAtOutlet($this->admin)
            ->post(route('bank.deposits.store'), [
                '_form' => 'setoran',
                'occurred_on' => now()->toDateString(),
                'amount' => 25000,
                'notes' => 'Setor pagi',
            ])
            ->assertRedirect(route('bank.deposits.create'));

        $this->actingAsAtOutlet($this->admin)
            ->get(route('bank.deposits.create'))
            ->assertOk()
            ->assertSee('Riwayat setoran kas')
            ->assertSee('Catat setoran')
            ->assertSee('Setor pagi');
    }
}
