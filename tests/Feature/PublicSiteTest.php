<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_homepage_is_single_landing_with_menu_location_vip_contact(): void
    {
        config(['site.whatsapp' => '6281234567890']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('XIWAY')
            ->assertSee('COFFEE')
            ->assertSee('id="menu"', false)
            ->assertSee('id="lokasi"', false)
            ->assertSee('id="vip"', false)
            ->assertSee('id="kontak"', false)
            ->assertSee($this->sellableProduct->name)
            ->assertSee('Reservasi ruang privat')
            ->assertSee('Chat admin XIWAY')
            ->assertDontSee('Menu mitra')
            ->assertDontSee('Komisi cafe');
    }

    public function test_public_menu_redirects_to_home_anchor(): void
    {
        $this->get(route('site.menu'))
            ->assertRedirect(route('home').'#menu');
    }
}
