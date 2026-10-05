<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Bundle;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_admin_can_create_bundle(): void
    {
        $this->actingAsAtOutlet($this->admin)
            ->get(route('marketing.bundles'))
            ->assertOk()
            ->assertSee('Tambah bundle');

        $this->actingAsAtOutlet($this->admin)
            ->post(route('marketing.bundles.store'), [
                'name' => 'Paket Hemat',
                'price' => 50000,
                'items' => [
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1],
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('marketing.bundles'));

        $this->assertDatabaseHas('bundles', ['name' => 'Paket Hemat']);

        $this->actingAsAtOutlet($this->cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Paket Hemat')
            ->assertSee("category === 'bundle'", false);
    }

    public function test_admin_can_update_bundle(): void
    {
        $bundle = Bundle::query()->create([
            'name' => 'Paket Lama',
            'price' => 50000,
            'is_active' => true,
        ]);
        $bundle->items()->create(['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $bundle->items()->create(['product_id' => $this->sellableProduct->id, 'quantity' => 1]);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('marketing.bundles'))
            ->assertOk()
            ->assertSee('Edit');

        $this->actingAsAtOutlet($this->admin)
            ->put(route('marketing.bundles.update', $bundle), [
                'name' => 'Paket Baru',
                'campaign' => 'After School',
                'price' => 40000,
                'weekdays_only' => 1,
                'items' => [
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 2],
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1, 'choice_group' => 'snack'],
                ],
            ])
            ->assertRedirect(route('marketing.bundles'));

        $bundle->refresh();
        $this->assertSame('Paket Baru', $bundle->name);
        $this->assertSame('After School', $bundle->campaign);
        $this->assertTrue($bundle->weekdays_only);
        $this->assertSame(2, $bundle->items()->count());
        $this->assertSame('snack', $bundle->items()->whereNotNull('choice_group')->value('choice_group'));

        $this->actingAsAtOutlet($this->cashier)
            ->put(route('marketing.bundles.update', $bundle), [
                'name' => 'Paket Kasir',
                'price' => 1000,
                'items' => [
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1],
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertForbidden();
    }

    public function test_bundle_scheduled_for_jakarta_today_shows_on_pos(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 18:30:00', 'UTC'));

        Bundle::query()->create([
            'name' => 'Paket Malam',
            'price' => 10000,
            'is_active' => true,
            'start_date' => '2026-10-06',
            'start_time' => '01:00:00',
            'end_time' => '02:00:00',
        ]);

        $this->actingAsAtOutlet($this->cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Paket Malam');
    }

    public function test_cashier_cannot_create_bundle(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->post(route('marketing.bundles.store'), [
                'name' => 'Paket Kasir',
                'price' => 10000,
                'items' => [
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1],
                    ['product_id' => $this->sellableProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertForbidden();
    }

    public function test_cashier_cannot_access_settings(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_settings(): void
    {
        $this->actingAsAtOutlet($this->admin)
            ->get(route('settings.index'))
            ->assertOk();
    }

    public function test_cashier_does_not_see_kitchen_bar_or_tables_menus(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('kitchen.index', absolute: false))
            ->assertDontSee(route('bar.index', absolute: false))
            ->assertDontSee(route('tables.index', absolute: false))
            ->assertDontSee(route('customers.index', absolute: false))
            ->assertDontSee(route('loyalty.index', absolute: false));
    }

    public function test_cashier_can_access_pos(): void
    {
        $this->assertTrue($this->cashier->hasPermission('pos.access'));

        $this->actingAsAtOutlet($this->cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertDontSee('Member baru')
            ->assertDontSee('Walk-in');
    }

    public function test_cashier_can_advance_order_status(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->submit($order);

        $this->actingAsAtOutlet($this->cashier)
            ->from(route('orders.show', $order))
            ->post(route('orders.status', $order), ['status' => 'processing'])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_cannot_mark_order_ready_until_all_items_ready(): void
    {
        $this->actingAsAtOutlet($this->cashier);
        $orders = app(OrderService::class);
        $order = $orders->createDraft([
            'outlet_id' => $this->outlet->id,
            'order_type' => OrderType::Pickup->value,
        ]);
        $first = $orders->addItem($order, ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $second = $orders->addItem($order->fresh(), ['product_id' => $this->sellableProduct->id, 'quantity' => 1]);
        $orders->submit($order->fresh());

        $this->from(route('orders.show', $order))
            ->post(route('orders.status', $order), ['status' => 'ready'])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->post(route('order-items.status', $first), ['status' => 'ready']);
        $this->post(route('order-items.status', $second), ['status' => 'ready']);

        $this->from(route('orders.show', $order))
            ->post(route('orders.status', $order), ['status' => 'ready'])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
    }
}
