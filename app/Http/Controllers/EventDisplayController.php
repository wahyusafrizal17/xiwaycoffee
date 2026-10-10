<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventDisplayController extends Controller
{
    public const SETTING_KEY = 'event_display_image';

    public const DEFAULT_IMAGE = 'images/events/default.jpg';

    public const MAX_IMAGES = 12;

    public function show(): View
    {
        return view('event-display.show', [
            'imageUrls' => $this->imageUrls(),
        ]);
    }

    public function edit(): View
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $paths = $this->imagePaths();

        return view('event-display.edit', [
            'images' => collect($paths)->map(fn (string $path, int $index) => [
                'index' => $index,
                'url' => $this->urlFor($path, $index),
            ])->filter(fn (array $image) => $image['url'] !== null)->values(),
            'displayUrl' => route('event-display.show'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'max:5120'],
        ], [
            'images.required' => 'Pilih gambar event terlebih dahulu.',
            'images.*.image' => 'File harus berupa gambar.',
            'images.*.max' => 'Ukuran tiap gambar maksimal 5 MB.',
        ]);

        $paths = $this->imagePaths();
        if (count($paths) + count($data['images']) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'Maksimal '.self::MAX_IMAGES.' gambar event.',
            ]);
        }

        foreach ($data['images'] as $image) {
            $paths[] = $image->store('events', 'public');
        }

        $this->savePaths($paths);

        return redirect()
            ->route('event-display.edit')
            ->with('success', 'Gambar event ditambahkan.');
    }

    public function destroy(int $index): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $paths = $this->imagePaths();
        if (! isset($paths[$index])) {
            throw ValidationException::withMessages([
                'images' => 'Gambar tidak ditemukan.',
            ]);
        }

        $removed = $paths[$index];
        array_splice($paths, $index, 1);
        $this->savePaths($paths);

        if (! str_starts_with($removed, 'http://') && ! str_starts_with($removed, 'https://') && Storage::disk('public')->exists($removed)) {
            Storage::disk('public')->delete($removed);
        }

        return redirect()
            ->route('event-display.edit')
            ->with('success', 'Gambar event dihapus.');
    }

    public function file(?int $index = 0): BinaryFileResponse
    {
        $paths = $this->imagePaths();
        $path = $paths[$index] ?? null;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * @return list<string>
     */
    public function imageUrls(): array
    {
        $urls = [];
        foreach ($this->imagePaths() as $index => $path) {
            $url = $this->urlFor($path, $index);
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls !== [] ? $urls : [asset(self::DEFAULT_IMAGE)];
    }

    /**
     * Saved list, or the previous single path until more images are added.
     *
     * @return list<string>
     */
    public function imagePaths(): array
    {
        $stored = setting(self::SETTING_KEY);
        if (! is_string($stored) || $stored === '') {
            return [];
        }

        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($path) => is_string($path) && $path !== ''));
        }

        return [$stored];
    }

    /**
     * @param  list<string>  $paths
     */
    protected function savePaths(array $paths): void
    {
        Setting::query()->updateOrCreate(
            ['outlet_id' => null, 'key' => self::SETTING_KEY],
            ['value' => json_encode(array_values($paths)), 'group' => 'display']
        );

        $this->forgetSettingCache();
    }

    protected function urlFor(string $path, int $index): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (is_file(public_path($path))) {
            return asset($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return route('event-display.file', $index);
        }

        return null;
    }

    protected function forgetSettingCache(): void
    {
        Cache::forget('setting..'.self::SETTING_KEY);
        Cache::forget('setting.'.current_outlet_id().'.'.self::SETTING_KEY);
    }
}
