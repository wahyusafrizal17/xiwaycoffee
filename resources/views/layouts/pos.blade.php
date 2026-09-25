<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.RasaQz = { printer: @json($qzPrinter ?? setting('qz_printer', '')) };</script>
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.5/qz-tray.js"></script>
    @include('layouts.partials.qz-print')
</head>
<body class="h-screen overflow-hidden bg-[#f5f2ee] text-ink">
    <div class="flex h-screen flex-col">
        <header class="relative z-40 flex h-14 shrink-0 items-center justify-between gap-3 overflow-visible border-b border-[#ebe7e2] bg-white/90 px-4 backdrop-blur">
            <div class="flex shrink-0 items-center gap-3">
                <img src="{{ asset('images/logo/logo.png') }}" alt="Xiway Pos" class="h-12 w-auto max-w-[140px] object-contain">
            </div>
            <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5 sm:gap-2">
                @if (auth()->user()->canSwitchOutlet())
                    <form method="GET" action="{{ url()->current() }}" class="min-w-0 max-w-[42vw] sm:max-w-none">
                        @foreach (request()->except('switch_outlet') as $k => $v)
                            @if (!is_array($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                        @endforeach
                        <select name="switch_outlet" onchange="this.form.submit()" class="input !w-full !max-w-[180px] !rounded-xl !border-[#ebe7e2] !bg-[#faf9f7] !py-1.5 !text-[13px] sm:!w-auto">
                            @foreach (\App\Models\Outlet::query()->where('is_active', true)->get() as $outlet)
                                <option value="{{ $outlet->id }}" @selected(current_outlet_id() === $outlet->id)>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ route('dashboard') }}" class="btn-ghost !hidden !rounded-xl !px-3 !py-2 text-xs sm:!inline-flex">Dashboard</a>
                <a href="{{ route('orders.index') }}" class="btn-ghost !hidden !rounded-xl !px-3 !py-2 text-xs sm:!inline-flex">Orders</a>
                <div class="shrink-0">
                    @include('layouts.partials.user-menu')
                </div>
            </div>
        </header>

        <main class="min-h-0 flex-1">
            @if (session('success'))
                <div class="absolute left-1/2 top-16 z-30 -translate-x-1/2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 3200)">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="absolute left-1/2 top-16 z-30 -translate-x-1/2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
