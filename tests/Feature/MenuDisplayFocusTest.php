<?php

namespace Tests\Feature;

use App\Http\Controllers\MenuDisplayController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class MenuDisplayFocusTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
        Cache::forget(MenuDisplayController::FOCUS_KEY);
    }

    public function test_display_page_exposes_focus_polling(): void
    {
        $this->get(route('menu.display'))
            ->assertOk()
            ->assertSee('FOCUS_URL', false)
            ->assertSee('POLL_MS', false);
    }

    public function test_cashier_can_set_display_focus_and_tv_can_poll_it(): void
    {
        $this->get(route('menu.display.focus'))
            ->assertOk()
            ->assertJsonPath('mode', 'auto');

        $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('pos.display.focus'), ['mode' => 'drinks'])
            ->assertOk()
            ->assertJsonPath('mode', 'drinks');

        $this->get(route('menu.display.focus'))
            ->assertOk()
            ->assertJsonPath('mode', 'drinks');

        $this->actingAsAtOutlet($this->cashier)
            ->postJson(route('pos.display.focus'), ['mode' => 'food'])
            ->assertOk()
            ->assertJsonPath('mode', 'food');
    }
}
