<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EventDisplayController extends Controller
{
    public const SETTING_KEY = 'event_display_image';

    public const DEFAULT_IMAGE = 'images/events/default.jpg';

    public function show(): View
    {
        return view('event-display.show', [
            'imageUrl' => $this->imageUrl(),
        ]);
    }

    public function edit(): View
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        return view('event-display.edit', [
            'imageUrl' => $this->imageUrl(),
            'displayUrl' => route('event-display.show'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ], [
            'image.required' => 'Pilih gambar event terlebih dahulu.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
        ]);

        $previous = setting(self::SETTING_KEY);
        $path = $data['image']->store('events', 'public');

        Setting::query()->updateOrCreate(
            ['outlet_id' => null, 'key' => self::SETTING_KEY],
            ['value' => $path, 'group' => 'display']
        );

        $this->forgetSettingCache();

        if (is_string($previous) && $previous !== '' && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return redirect()
            ->route('event-display.edit')
            ->with('success', 'Gambar event diperbarui.');
    }

    public function imageUrl(): string
    {
        $path = setting(self::SETTING_KEY);

        if (is_string($path) && $path !== '') {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (Storage::disk('public')->exists($path)) {
                return asset('storage/'.$path);
            }
        }

        return asset(self::DEFAULT_IMAGE);
    }

    protected function forgetSettingCache(): void
    {
        Cache::forget('setting..'.self::SETTING_KEY);
        Cache::forget('setting.'.current_outlet_id().'.'.self::SETTING_KEY);
    }
}
