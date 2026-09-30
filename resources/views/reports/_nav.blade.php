@php
    $tab = 'inline-flex items-center rounded-lg px-3 py-1.5 text-[13px] font-medium';
    $tabOn = 'bg-[#111] text-white';
    $tabOff = 'bg-[#f5f5f5] text-muted hover:bg-[#ececec]';
    $scope = array_filter(request()->only(['from', 'to', 'outlet_id']));
    if (isset($range['from'], $range['to'])) {
        $scope['from'] = $range['from'];
        $scope['to'] = $range['to'];
    }
    $periodScope = $scope;
    if (isset($range['period'])) {
        $periodScope['period'] = $range['period'];
        $key = match ($range['period']) {
            'day' => 'date',
            'month' => 'month',
            'year' => 'year',
            default => null,
        };
        if ($key) {
            $periodScope[$key] = $range[$key] ?? null;
            unset($periodScope['from'], $periodScope['to']);
        }
    }
    $scope = array_filter($scope);
    $periodScope = array_filter($periodScope);
@endphp
@if (auth()->user()->hasPermission('reports.view'))
<div class="mb-5 flex flex-wrap gap-1.5">
    <a href="{{ route('reports.sales', $periodScope) }}" class="{{ $tab }} {{ request()->routeIs('reports.sales') ? $tabOn : $tabOff }}">Sales</a>
    <a href="{{ route('reports.products', $periodScope) }}" class="{{ $tab }} {{ request()->routeIs('reports.products') ? $tabOn : $tabOff }}">Products</a>
    <a href="{{ route('reports.inventory') }}" class="{{ $tab }} {{ request()->routeIs('reports.inventory') ? $tabOn : $tabOff }}">Inventory</a>
    <a href="{{ route('reports.setoran', $periodScope) }}" class="{{ $tab }} {{ request()->routeIs('reports.setoran') ? $tabOn : $tabOff }}">Setoran Makanan</a>
</div>
@endif
