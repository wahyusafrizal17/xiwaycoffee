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

    public function test_homepage_is_public_and_shows_brand(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('XIWAY COFFEE')
            ->assertSee('Lebih dari')
            ->assertSee('Specialty Arabika Gayo')
            ->assertSee('Temukan XIWAY')
            ->assertSee('Explore Menu');
    }

    public function test_public_menu_lists_products_without_kitchen_notes(): void
    {
        $this->get(route('site.menu'))
            ->assertOk()
            ->assertSee('What’s brewing')
            ->assertSee($this->sellableProduct->name)
            ->assertDontSee('Menu mitra')
            ->assertDontSee('Komisi cafe');
    }
}
