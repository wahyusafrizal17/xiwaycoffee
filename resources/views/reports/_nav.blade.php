@php
    $tab = 'inline-flex items-center rounded-lg px-3 py-1.5 text-[13px] font-medium';
    $tabOn = 'bg-[#111] text-white';
    $tabOff = 'bg-[#f5f5f5] text-muted hover:bg-[#ececec]';
    $scope = request()->only(['from', 'to', 'period', 'outlet_id']);
@endphp
<div class="mb-5 flex flex-wrap gap-1.5">
    <a href="{{ route('reports.sales', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.sales') ? $tabOn : $tabOff }}">Sales</a>
    <a href="{{ route('reports.products', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.products') ? $tabOn : $tabOff }}">Products</a>
    <a href="{{ route('reports.categories', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.categories') ? $tabOn : $tabOff }}">Categories</a>
    <a href="{{ route('reports.promo', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.promo') ? $tabOn : $tabOff }}">Promo</a>
    <a href="{{ route('reports.inventory') }}" class="{{ $tab }} {{ request()->routeIs('reports.inventory') ? $tabOn : $tabOff }}">Inventory</a>
    <a href="{{ route('reports.movements', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.movements') ? $tabOn : $tabOff }}">Movements</a>
    <a href="{{ route('reports.production', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.production') ? $tabOn : $tabOff }}">Production</a>
    <a href="{{ route('reports.waste', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.waste') ? $tabOn : $tabOff }}">Waste</a>
    <a href="{{ route('reports.expenses') }}" class="{{ $tab }} {{ request()->routeIs('reports.expenses') ? $tabOn : $tabOff }}">BOP</a>
    <a href="{{ route('reports.setoran', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.setoran') ? $tabOn : $tabOff }}">Setoran makanan</a>
    <a href="{{ route('reports.profit', $scope) }}" class="{{ $tab }} {{ request()->routeIs('reports.profit') ? $tabOn : $tabOff }}">Bagi hasil</a>
</div>
