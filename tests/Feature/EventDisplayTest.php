<?php

namespace Tests\Feature;

use App\Http\Controllers\EventDisplayController;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

        $this->post(route('event-display.update'), ['images' => [$file]])
            ->assertRedirect(route('event-display.edit'));

        $paths = json_decode((string) setting(EventDisplayController::SETTING_KEY), true);
        $this->assertCount(1, $paths);
        Storage::disk('public')->assertExists($paths[0]);

        $this->get(route('event-display.show'))
            ->assertOk()
            ->assertSee(route('event-display.file', 0), false);

        $this->get(route('event-display.file'))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_admin_can_upload_several_images_and_the_display_rotates_them(): void
    {
        Storage::fake('public');
        $this->actingAsAtOutlet($this->admin);

        $this->post(route('event-display.update'), [
            'images' => [
                UploadedFile::fake()->image('satu.jpg', 800, 1200),
                UploadedFile::fake()->image('dua.jpg', 800, 1200),
            ],
        ])->assertRedirect(route('event-display.edit'));

        $this->get(route('event-display.show'))
            ->assertOk()
            ->assertSee(route('event-display.file', 0), false)
            ->assertSee(route('event-display.file', 1), false)
            ->assertSee('setInterval', false);

        $this->get(route('event-display.file', 1))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->delete(route('event-display.destroy', 0))
            ->assertRedirect(route('event-display.edit'));

        $paths = json_decode((string) setting(EventDisplayController::SETTING_KEY), true);
        $this->assertCount(1, $paths);

        $this->get(route('event-display.show'))
            ->assertOk()
            ->assertDontSee('setInterval', false);
    }

    public function test_legacy_single_image_setting_still_shows(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('lama.jpg')->store('events', 'public');

        Setting::query()->create([
            'outlet_id' => null,
            'key' => EventDisplayController::SETTING_KEY,
            'value' => $path,
            'group' => 'display',
        ]);
        Cache::forget('setting..'.EventDisplayController::SETTING_KEY);

        $this->get(route('event-display.show'))
            ->assertOk()
            ->assertSee(route('event-display.file', 0), false)
            ->assertDontSee('setInterval', false);
    }

    public function test_cashier_cannot_manage_event_display(): void
    {
        $this->actingAsAtOutlet($this->cashier);

        $this->get(route('event-display.edit'))->assertForbidden();
    }
}
