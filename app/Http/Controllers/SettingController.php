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
        'points_earn_per_amount',
        'points_redeem_value',
        'company_name',
        'receipt_footer',
        'qz_printer',
    ];

    protected array $defaults = [
        'tax_rate' => 11,
        'service_charge' => 0,
        'points_earn_per_amount' => 10000,
        'points_redeem_value' => 100,
        'company_name' => 'Rasa',
        'receipt_footer' => 'Terima kasih',
        'qz_printer' => '',
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
                'points_earn_per_amount' => (int) $settings['points_earn_per_amount'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $data = $request->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'points_earn_per_amount' => ['required', 'integer', 'min:1'],
            'points_redeem_value' => ['required', 'integer', 'min:1'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'qz_printer' => ['nullable', 'string', 'max:120'],
        ], [
            'tax_rate.required' => 'Tarif pajak wajib diisi.',
            'tax_rate.max' => 'Tarif pajak maksimal 100%.',
            'points_earn_per_amount.required' => 'Nominal poin wajib diisi.',
            'points_redeem_value.required' => 'Nilai tukar poin wajib diisi.',
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
