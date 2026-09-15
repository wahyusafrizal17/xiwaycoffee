<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeedsPosFixture;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPosFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPosFixture();
    }

    public function test_user_can_view_profile_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profil saya')
            ->assertSee('Unggah foto');
    }

    public function test_user_can_update_profile_details(): void
    {
        $this->actingAs($this->admin)
            ->put(route('profile.update'), [
                'name' => 'Admin Rasa',
                'email' => 'admin@example.com',
                'phone' => '081234567890',
            ])
            ->assertRedirect();

        $this->admin->refresh();

        $this->assertSame('Admin Rasa', $this->admin->name);
        $this->assertSame('081234567890', $this->admin->phone);
    }

    public function test_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 240, 240);

        $this->actingAs($this->admin)
            ->put(route('profile.update'), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'phone' => $this->admin->phone,
                'avatar' => $file,
            ])
            ->assertRedirect();

        $this->admin->refresh();
        $this->assertNotNull($this->admin->avatar);
        Storage::disk('public')->assertExists($this->admin->avatar);
        $this->assertNotNull($this->admin->avatarUrl());

        $this->actingAs($this->admin)
            ->put(route('profile.update'), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'phone' => $this->admin->phone,
                'remove_avatar' => '1',
            ])
            ->assertRedirect();

        $this->admin->refresh();
        $this->assertNull($this->admin->avatar);
        $this->assertNull($this->admin->avatarUrl());
    }

    public function test_user_can_change_password(): void
    {
        $this->actingAs($this->admin)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect();

        $this->admin->refresh();
        $this->assertTrue(Hash::check('new-password', $this->admin->password));
    }
}
