<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WaCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    protected array $keys = [
        'service_charge',
    ];

    protected array $defaults = [
        'service_charge' => 0,
    ];

    public function index(WaCloudService $wa): View
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        $stored = Setting::query()->whereNull('outlet_id')->whereIn('key', $this->keys)->pluck('value', 'key');
        $settings = collect($this->keys)->mapWithKeys(fn ($key) => [
            $key => old($key, $stored[$key] ?? $this->defaults[$key] ?? ''),
        ]);

        return view('settings.index', [
            'settings' => $settings,
            'kitchenPhones' => old('kitchen_whatsapp', $wa->kitchenPhones()) ?: [''],
            'stats' => [
                'service_charge' => (float) $settings['service_charge'],
            ],
        ]);
    }

    public function update(Request $request, WaCloudService $wa): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $data = $request->validate([
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'kitchen_whatsapp' => ['nullable', 'array'],
            'kitchen_whatsapp.*' => ['nullable', 'string', 'max:30'],
        ]);

        if ($request->has('kitchen_whatsapp')) {
            $phones = collect($data['kitchen_whatsapp'])->filter()->map(fn ($phone) => $wa->normalizePhone($phone));
            if ($phones->contains('')) {
                throw ValidationException::withMessages(['kitchen_whatsapp' => 'Ada nomor WhatsApp yang tidak valid.']);
            }
            $data['kitchen_whatsapp'] = $phones->unique()->implode(',');
        }

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
