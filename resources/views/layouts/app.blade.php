<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas text-ink" x-data="{ sidebar: false, collapsed: localStorage.getItem('sidebar') === '1' }" x-init="$watch('collapsed', v => localStorage.setItem('sidebar', v ? '1' : '0'))">
    <div class="flex min-h-screen">
        <div class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" x-show="sidebar" x-cloak @click="sidebar = false"></div>

        <aside class="fixed inset-y-0 left-0 z-40 flex w-[260px] -translate-x-full flex-col bg-sidebar text-white transition-all duration-200 lg:static lg:translate-x-0"
               :class="{
                    'translate-x-0': sidebar,
                    'lg:w-[78px]': collapsed,
                    'lg:w-[260px]': !collapsed
               }">
            <div class="flex h-[92px] items-center justify-between gap-2 px-3">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center">
                    <img src="{{ asset('images/logo/logo-white.png') }}" alt="Rasa POS" class="h-[76px] w-auto max-w-full object-contain object-left" x-show="!collapsed">
                    <img src="{{ asset('images/logo/logo-white.png') }}" alt="Rasa POS" class="h-10 w-10 object-contain" x-show="collapsed" x-cloak>
                </a>
                <button class="hidden rounded-md p-1 text-[#8a8d9f] hover:bg-white/5 lg:inline-flex" @click="collapsed = !collapsed">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h10M4 18h16"/></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-8">
                @php
                    $groups = [
                        'Operations' => [
                            ['label' => 'Dashboard', 'route' => 'dashboard', 'perm' => 'dashboard.view', 'icon' => 'home'],
                            ['label' => 'POS', 'route' => 'pos.index', 'perm' => 'pos.access', 'icon' => 'pos'],
                            ['label' => 'Orders', 'route' => 'orders.index', 'perm' => 'orders.view', 'icon' => 'orders'],
                            ['label' => 'Absensi', 'route' => 'attendance.index', 'perm' => 'attendance.clock', 'icon' => 'clipboard'],
                            ['label' => 'BOP', 'route' => 'reports.expenses', 'perm' => 'bop.manage', 'icon' => 'clipboard'],
                        ],
                        'Commerce' => [
                            ['label' => 'Marketing', 'route' => 'marketing.discounts', 'perm' => 'marketing.view', 'icon' => 'marketing', 'children' => [
                                ['label' => 'Discounts', 'route' => 'marketing.discounts', 'icon' => 'tag'],
                                ['label' => 'Bundles', 'route' => 'marketing.bundles', 'icon' => 'gift'],
                            ]],
                            ['label' => 'Catalog', 'route' => 'products.index', 'perm' => 'products.view', 'icon' => 'products', 'children' => [
                                ['label' => 'Products', 'route' => 'products.index', 'icon' => 'products'],
                                ['label' => 'Categories', 'route' => 'categories.index', 'icon' => 'folder'],
                            ]],
                        ],
                        'Supply' => [
                            ['label' => 'Inventory', 'route' => 'inventory.index', 'perm' => 'inventory.view', 'icon' => 'inventory', 'children' => [
                                ['label' => 'Stock', 'route' => 'inventory.index', 'icon' => 'inventory'],
                                ['label' => 'Movements', 'route' => 'inventory.movements', 'icon' => 'arrows'],
                                ['label' => 'Stock Opname', 'route' => 'opnames.index', 'icon' => 'clipboard-check'],
                                ['label' => 'Waste', 'route' => 'wastes.index', 'icon' => 'trash'],
                                ['label' => 'Transfers', 'route' => 'transfers.index', 'icon' => 'swap'],
                            ]],
                            ['label' => 'Production', 'route' => 'production.index', 'perm' => 'production.view', 'icon' => 'production', 'children' => [
                                ['label' => 'Orders', 'route' => 'production.index', 'icon' => 'clipboard'],
                                ['label' => 'BOM', 'route' => 'boms.index', 'icon' => 'list'],
                                ['label' => 'Batches', 'route' => 'batches.index', 'icon' => 'layers'],
                            ]],
                        ],
                        'System' => [
                            ['label' => 'Reports', 'route' => 'reports.sales', 'perm' => 'reports.view', 'icon' => 'reports', 'children' => [
                                ['label' => 'Sales', 'route' => 'reports.sales', 'icon' => 'chart'],
                                ['label' => 'Products', 'route' => 'reports.products', 'icon' => 'products'],
                                ['label' => 'Categories', 'route' => 'reports.categories', 'icon' => 'folder'],
                                ['label' => 'Promo', 'route' => 'reports.promo', 'icon' => 'megaphone'],
                                ['label' => 'BOP', 'route' => 'reports.expenses', 'icon' => 'clipboard'],
                                ['label' => 'Setoran makanan', 'route' => 'reports.setoran', 'icon' => 'clipboard'],
                                ['label' => 'Bagi hasil', 'route' => 'reports.profit', 'icon' => 'chart'],
                                ['label' => 'Investor', 'route' => 'investors.index', 'icon' => 'user'],
                                ['label' => 'Undangan', 'route' => 'invites.index', 'icon' => 'megaphone'],
                            ]],
                            ['label' => 'Printers', 'route' => 'printers.index', 'perm' => 'printers.view', 'icon' => 'printers'],
                            ['label' => 'Settings', 'route' => 'settings.index', 'perm' => 'settings.manage', 'icon' => 'settings', 'children' => [
                                ['label' => 'General', 'route' => 'settings.index', 'icon' => 'settings'],
                                ['label' => 'Outlets', 'route' => 'outlets.index', 'icon' => 'building'],
                                ['label' => 'Users', 'route' => 'users.index', 'icon' => 'user'],
                                ['label' => 'Audit Logs', 'route' => 'audit.index', 'icon' => 'document'],
                            ]],
                        ],
                    ];
                @endphp

                @foreach ($groups as $section => $items)
                    @php
                        $visible = collect($items)->contains(fn ($item) => auth()->user()->hasPermission($item['perm']));
                    @endphp
                    @if ($visible)
                        <p class="nav-section" x-show="!collapsed">{{ $section }}</p>
                        @foreach ($items as $item)
                            @if (auth()->user()->hasPermission($item['perm']))
                                @php
                                    $children = $item['children'] ?? [];
                                    $childRoutes = collect($children)->pluck('route')->all();
                                    $parentPattern = str($item['route'])->beforeLast('.')->append('.*')->toString();
                                    $groupActive = $children
                                        ? request()->routeIs($parentPattern, $item['route'], ...$childRoutes)
                                        : request()->routeIs($item['route']);
                                    $leafActive = empty($children) && $groupActive;
                                @endphp
                                <div class="{{ $children ? 'nav-group' : '' }}" x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }" @if ($children) :class="open && 'nav-group-open'" @endif>
                                    @if ($children)
                                        <button type="button" class="nav-item" @click="open = !open">
                                            @include('layouts.partials.icon', ['name' => $item['icon']])
                                            <span class="flex-1" x-show="!collapsed">{{ $item['label'] }}</span>
                                            <svg class="h-3.5 w-3.5 shrink-0 text-white/70 transition-transform" x-show="!collapsed" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9l6 6 6-6"/>
                                            </svg>
                                        </button>
                                        <div class="space-y-0.5 px-2 pb-2 pl-3" x-show="open && !collapsed">
                                            @foreach ($children as $child)
                                                @if (empty($child['perm']) || auth()->user()->hasPermission($child['perm']))
                                                    @php
                                                        $otherChildren = array_values(array_diff($childRoutes, [$child['route']]));
                                                        $childActive = request()->routeIs($child['route'])
                                                            || ($child['route'] === $item['route']
                                                                && request()->routeIs($parentPattern)
                                                                && ($otherChildren === [] || ! request()->routeIs(...$otherChildren)));
                                                    @endphp
                                                    <a href="{{ route($child['route']) }}" class="nav-subitem {{ $childActive ? 'nav-subitem-active' : '' }}">
                                                        @include('layouts.partials.icon', ['name' => $child['icon'] ?? 'clipboard', 'size' => 'h-4 w-4'])
                                                        <span>{{ $child['label'] }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <a href="{{ route($item['route']) }}" class="nav-item {{ $leafActive ? 'nav-item-active' : '' }}">
                                            @include('layouts.partials.icon', ['name' => $item['icon']])
                                            <span class="flex-1" x-show="!collapsed">{{ $item['label'] }}</span>
                                        </a>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </nav>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 mx-3 mt-3 flex h-16 items-center justify-between rounded-xl bg-white px-4 shadow-[0_4px_18px_rgba(47,43,61,0.10)] lg:mx-6 lg:px-5">
                <div class="flex items-center gap-3">
                    <button class="rounded-lg border border-line p-2 text-heading lg:hidden" @click="sidebar = !sidebar">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
                <div class="flex items-center gap-2.5">
                    <div
                        class="relative"
                        x-data="lowStockAlerts()"
                        x-init="load()"
                    >
                        <button type="button" class="relative rounded-lg border border-line p-2 text-heading hover:bg-slate-50" @click="open = !open">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                            <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-white" x-show="count > 0" x-text="count" x-cloak></span>
                        </button>
                        <div class="absolute right-0 z-30 mt-2 w-72 rounded-xl border border-line bg-white p-3 shadow-lg" x-show="open" x-cloak @click.outside="open = false">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Stok menipis</p>
                            <div class="mt-2 max-h-64 space-y-2 overflow-y-auto">
                                <template x-for="item in items" :key="item.id">
                                    <div class="rounded-lg bg-brand-soft px-3 py-2 text-xs">
                                        <p class="font-medium text-heading" x-text="item.name"></p>
                                        <p class="text-muted" x-text="`Sisa ${item.quantity} ${item.unit || ''} · reorder ${item.reorder_level}`"></p>
                                    </div>
                                </template>
                                <p class="py-6 text-center text-xs text-muted" x-show="!items.length">Tidak ada stok di bawah reorder level.</p>
                            </div>
                        </div>
                    </div>
                    @if (auth()->user()->canSwitchOutlet())
                        <form method="GET" action="{{ url()->current() }}">
                            @foreach (request()->except('switch_outlet') as $k => $v)
                                @if (!is_array($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                            @endforeach
                            <select name="switch_outlet" onchange="this.form.submit()" class="input !w-auto !rounded-lg !py-2 !text-[13px]">
                                @foreach (\App\Models\Outlet::query()->where('is_active', true)->get() as $outlet)
                                    <option value="{{ $outlet->id }}" @selected(current_outlet_id() === $outlet->id)>{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    @include('layouts.partials.user-menu')
                </div>
            </header>

            <main class="flex-1 px-3 py-5 lg:px-6">
                @include('layouts.partials.page-header')
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 3200)">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-line bg-white px-2 py-2 lg:hidden">
        @if (auth()->user()?->hasPermission('dashboard.view'))
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 text-[11px] {{ request()->routeIs('dashboard') ? 'text-brand' : 'text-muted' }}">Home</a>
        @elseif (auth()->user()?->hasPermission('attendance.clock'))
            <a href="{{ route('attendance.index') }}" class="flex flex-col items-center gap-1 text-[11px] {{ request()->routeIs('attendance.*') ? 'text-brand' : 'text-muted' }}">Absen</a>
        @endif
        @if (auth()->user()?->can('pos.access'))
            <a href="{{ route('pos.index') }}" class="flex flex-col items-center gap-1 text-[11px] {{ request()->routeIs('pos.*') ? 'text-brand' : 'text-muted' }}">POS</a>
        @endif
        @can('orders.view')<a href="{{ route('orders.index') }}" class="flex flex-col items-center gap-1 text-[11px] {{ request()->routeIs('orders.*') ? 'text-brand' : 'text-muted' }}">Orders</a>@endcan
        <a href="{{ route('profile.edit') }}" class="flex flex-col items-center gap-1 text-[11px] {{ request()->routeIs('profile.*') ? 'text-brand' : 'text-muted' }}">Me</a>
    </nav>

    @if (auth()->user()?->hasPermission('orders.check') && request()->routeIs('kitchen.*', 'bar.*'))
        <script>window.RasaQz = { printer: '' };</script>
        <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.5/qz-tray.js"></script>
        @include('layouts.partials.qz-print')
        @include('layouts.partials.station-autoprint')
    @endif
    @include('layouts.partials.select2')
    @livewireScripts
    <script>
        document.querySelector('script[data-update-uri="/livewire/update"]')
            ?.setAttribute('data-update-uri', @json(parse_url(url('/livewire/update'), PHP_URL_PATH)));
    </script>
    <script>
        function lowStockAlerts() {
            return {
                open: false,
                count: 0,
                items: [],
                async load() {
                    try {
                        const res = await fetch('{{ route('alerts.low-stock') }}', { headers: { 'Accept': 'application/json' } });
                        if (! res.ok) return;
                        const data = await res.json();
                        this.count = data.count || 0;
                        this.items = data.items || [];
                    } catch (e) {}
                    setTimeout(() => this.load(), 60000);
                },
            };
        }
    </script>
    @stack('scripts')
</body>
</html>
