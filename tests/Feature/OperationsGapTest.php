<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Unit;
use App\Services\OrderService;
use App\Services\ProductionService;
use App\Services\TableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class OperationsGapTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_pickup_draft_uses_pickup_channel(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $order = app(OrderService::class)->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);

        $this->assertSame(OrderChannel::Pickup, $order->channel);
    }

    public function test_table_split_moves_selected_items(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);

        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->tableA->id,
            'order_type' => OrderType::DineIn->value,
        ]);
        $first = $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $second = $orders->addItem($order->fresh(), ['product_id' => $this->sellableProduct->id, 'quantity' => 2]);

        $split = app(TableService::class)->split($this->tableA->id, $this->tableB->id, [$second->id]);

        $this->assertSame($this->tableB->id, $split->table_id);
        $this->assertTrue($split->items->contains('id', $second->id));
        $this->assertTrue($order->fresh()->items->contains('id', $first->id));
        $this->assertFalse($order->fresh()->items->contains('id', $second->id));
        $this->assertSame(TableStatus::Occupied, $this->tableA->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $this->tableB->fresh()->status);
    }

    public function test_apply_points_from_pos_route(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $this->customer->update(['points' => 200]);

        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'customer_id' => $this->customer->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 2]);

        $this->postJson(route('pos.points', $order), ['points' => 100])
            ->assertOk()
            ->assertJsonPath('points_redeemed', 100);

        $this->assertEquals(10000, (float) $order->fresh()->points_value);
    }

    public function test_cashier_can_checkout_and_send_to_kitchen(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $this->postJson(route('pos.checkout', $order), [
            'method' => 'qris',
            'tendered' => 50000,
        ])->assertOk()
            ->assertJsonPath('order.status', 'new')
            ->assertJsonPath('order.payment_status', 'paid');
    }

    public function test_kitchen_checker_can_confirm_item(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::DineIn->value,
            'table_id' => $this->tableA->id,
        ]);
        $item = $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->submit($order->fresh());

        $this->actingAsAtOutlet($this->cashier)
            ->post(route('order-items.status', $item), ['status' => 'ready'])
            ->assertRedirect();

        $this->assertSame('ready', $item->fresh()->status);
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_unit_conversion_between_same_family(): void
    {
        $gram = Unit::query()->create([
            'code' => 'G',
            'name' => 'Gram',
            'family' => 'weight',
            'conversion_factor' => 1,
        ]);

        $converted = $this->unitKg->convertTo($gram, 0.5);

        $this->assertEquals(500, $converted);
    }

    public function test_bom_explode_lists_levels(): void
    {
        $rows = app(ProductionService::class)->explode($this->bom, 2);

        $this->assertNotEmpty($rows);
        $this->assertSame(1, $rows[0]['level']);
        $this->assertContains($this->rawChicken->id, array_column($rows, 'product_id'));
    }

    public function test_category_and_promo_reports_are_reachable(): void
    {
        $this->actingAsAtOutlet($this->admin)
            ->get(route('reports.categories', ['period' => 'today']))
            ->assertOk();

        $this->actingAsAtOutlet($this->admin)
            ->get(route('reports.promo', ['period' => 'month']))
            ->assertOk();
    }

    public function test_dine_in_submit_does_not_require_a_table(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::DineIn->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $this->postJson(route('pos.submit', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'new');

        $this->assertNull($order->fresh()->table_id);
        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }

    public function test_pos_can_list_and_recall_held_orders(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 2]);

        $this->postJson(route('pos.hold', $order))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson(route('pos.hold', $order), ['name' => 'Budi'])
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('status', 'held')
            ->assertJsonPath('customer', 'Budi');

        $this->assertSame(OrderStatus::Held, $order->fresh()->status);
        $this->assertSame('Budi', $order->fresh()->notes);

        $this->getJson(route('pos.held'))
            ->assertOk()
            ->assertJsonFragment(['id' => $order->id, 'order_number' => $order->order_number, 'customer' => 'Budi']);

        $this->getJson(route('pos.recall', $order))
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('status', 'held');
    }

    public function test_pos_held_list_includes_drafts_with_items(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);

        $draft = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($draft, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $empty = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);

        $this->getJson(route('pos.held'))
            ->assertOk()
            ->assertJsonFragment(['id' => $draft->id, 'status' => 'draft'])
            ->assertJsonMissing(['id' => $empty->id]);

        $this->getJson(route('pos.recall', $draft))
            ->assertOk()
            ->assertJsonPath('id', $draft->id)
            ->assertJsonPath('status', 'draft');
    }

    public function test_orders_index_hides_draft_and_held_by_default(): void
    {
        $this->actingAsAtOutlet($this->admin);
        $orders = app(OrderService::class);

        $draft = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($draft, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $held = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($held, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->hold($held, 'Tamu Hold');

        $submitted = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($submitted, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->submit($submitted);

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertDontSee($draft->order_number)
            ->assertDontSee($held->order_number)
            ->assertSee($submitted->order_number);

        $this->get(route('orders.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee($draft->order_number);
    }

    public function test_merge_moves_items_and_frees_source_table(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);

        $source = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->tableA->id,
            'order_type' => OrderType::DineIn->value,
        ]);
        $sourceItem = $orders->addItem($source, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $target = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->tableB->id,
            'order_type' => OrderType::DineIn->value,
        ]);
        $targetItem = $orders->addItem($target, ['product_id' => $this->sellableProduct->id, 'quantity' => 2]);

        $this->post(route('tables.merge'), [
            'source_id' => $this->tableA->id,
            'target_id' => $this->tableB->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(TableStatus::Available, $this->tableA->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $this->tableB->fresh()->status);
        $this->assertTrue($target->fresh()->items->contains('id', $sourceItem->id));
        $this->assertTrue($target->fresh()->items->contains('id', $targetItem->id));
        $this->assertSame(OrderStatus::Cancelled, $source->fresh()->status);

        $html = $this->get(route('tables.index'))->assertOk()->getContent();
        $this->assertStringContainsString('2 item', $html);
        $this->assertStringContainsString('Gabung dari T-01', $html);
    }

    public function test_merge_requires_active_orders_on_both_tables(): void
    {
        $this->actingAsAtOutlet($this->admin);
        DiningTable::query()->whereKey($this->tableA->id)->update(['status' => TableStatus::Occupied]);

        $this->from(route('tables.index'))
            ->post(route('tables.merge'), [
                'source_id' => $this->tableA->id,
                'target_id' => $this->tableB->id,
            ])
            ->assertRedirect(route('tables.index'))
            ->assertSessionHasErrors();

        $this->assertSame(TableStatus::Occupied, $this->tableA->fresh()->status);
        $this->assertSame(TableStatus::Available, $this->tableB->fresh()->status);
    }

    public function test_tables_index_lists_active_reservations(): void
    {
        $this->actingAsAtOutlet($this->admin);

        app(TableService::class)->reserve([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->tableA->id,
            'guest_name' => 'Budi Reservasi',
            'guest_phone' => '0812555000',
            'guest_count' => 3,
            'reserved_at' => now()->addHour(),
            'notes' => 'Ulang tahun',
        ]);

        $this->get(route('tables.index'))
            ->assertOk()
            ->assertSee('Daftar reservasi')
            ->assertSee('Budi Reservasi')
            ->assertSee('T-01')
            ->assertSee('Ulang tahun');
    }

    public function test_table_move_script_uses_generated_url(): void
    {
        $this->actingAsAtOutlet($this->admin);

        $html = $this->get(route('tables.index'))->assertOk()->getContent();

        $this->assertStringContainsString(url('/tables'), $html);
        $this->assertStringNotContainsString("fetch('/tables/'", $html);
    }

    public function test_split_route_requires_items(): void
    {
        $this->actingAsAtOutlet($this->admin);

        DiningTable::query()->whereKey($this->tableA->id)->update(['status' => TableStatus::Occupied]);

        $this->post(route('tables.split'), [
            'source_id' => $this->tableA->id,
            'target_id' => $this->tableB->id,
            'item_ids' => [],
        ])->assertSessionHasErrors();
    }
}
