<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingController extends Controller
{
    protected array $keys = [
        'tax_rate',
        'service_charge',
    ];

    protected array $defaults = [
        'tax_rate' => 10,
        'service_charge' => 0,
    ];

    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $stored = Setting::query()->whereNull('outlet_id')->whereIn('key', $this->keys)->pluck('value', 'key');
        $settings = collect($this->keys)->mapWithKeys(fn ($key) => [
            $key => old($key, $stored[$key] ?? $this->defaults[$key] ?? ''),
        ]);

        return view('settings.index', [
            'settings' => $settings,
            'stats' => [
                'tax_rate' => (float) $settings['tax_rate'],
                'service_charge' => (float) $settings['service_charge'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $data = $request->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
        ], [
            'tax_rate.required' => 'Tarif charge wajib diisi.',
            'tax_rate.max' => 'Tarif charge maksimal 100%.',
        ]);

        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(
                ['outlet_id' => null, 'key' => $key],
                ['value' => $value, 'group' => 'general']
            );
            Cache::forget("setting..{$key}");
            Cache::forget('setting.'.current_outlet_id().".{$key}");
        }

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
