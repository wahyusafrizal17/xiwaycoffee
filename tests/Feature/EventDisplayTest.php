<?php

namespace Tests\Feature;

use App\Http\Controllers\EventDisplayController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class EventDisplayTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_public_display_event_shows_default_image(): void
    {
        $this->get(route('event-display.show'))
            ->assertOk()
            ->assertSee(asset('images/events/default.jpg'), false);
    }

    public function test_admin_can_upload_event_image(): void
    {
        Storage::fake('public');
        $this->actingAsAtOutlet($this->admin);

        $file = UploadedFile::fake()->image('nobar.jpg', 800, 1200);

        $this->post(route('event-display.update'), ['image' => $file])
            ->assertRedirect(route('event-display.edit'));

        $path = setting(EventDisplayController::SETTING_KEY);
        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);

        $this->get(route('event-display.show'))
            ->assertOk()
            ->assertSee(asset('storage/'.$path), false);
    }

    public function test_cashier_cannot_manage_event_display(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $this->get(route('event-display.edit'))->assertForbidden();
    }
}
