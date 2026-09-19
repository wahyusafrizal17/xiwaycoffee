<?php

namespace Tests\Feature;

use App\Models\InviteGuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class InviteGuestTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_public_invite_page_shows_guest_name_and_maps_note(): void
    {
        $guest = InviteGuest::query()->create([
            'name' => 'Pak Wahyu Saimanuddin, ST & Istri',
            'slug' => 'pak-wahyu-saimanuddin',
            'sort_order' => 1,
        ]);

        $this->get(route('invites.show', $guest->slug))
            ->assertOk()
            ->assertSee('Pak Wahyu Saimanuddin, ST & Istri')
            ->assertSee('Grand Opening XIWAY COFFEE')
            ->assertSee('https://share.google/489YxsQigXiiFQWzE', false)
            ->assertSee('Mohon dukungan ulasan positif di map');
    }

    public function test_admin_can_list_guests_with_share_links(): void
    {
        InviteGuest::query()->create([
            'name' => 'Bang Hafiz & Istri',
            'slug' => 'bang-hafiz',
            'sort_order' => 2,
        ]);

        $this->actingAsAtOutlet($this->admin)
            ->get(route('invites.index'))
            ->assertOk()
            ->assertSee('Bang Hafiz & Istri')
            ->assertSee('wa.me', false)
            ->assertSee(rawurlencode('Grand Opening XIWAY COFFEE'), false)
            ->assertSee(route('invites.show', 'bang-hafiz'), false);
    }

    public function test_cashier_cannot_open_invite_list(): void
    {
        $this->actingAsAtOutlet($this->cashier)
            ->get(route('invites.index'))
            ->assertForbidden();
    }
}
