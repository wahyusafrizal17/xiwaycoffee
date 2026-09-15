@php $period = $filters['period'] ?? null; @endphp
<div class="mb-3 flex flex-wrap gap-2">
    <a href="{{ request()->fullUrlWithQuery(['period' => 'today', 'from' => null, 'to' => null]) }}" class="rounded-full px-3 py-1 text-xs {{ $period === 'today' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600' }}">Hari ini</a>
    <a href="{{ request()->fullUrlWithQuery(['period' => 'week', 'from' => null, 'to' => null]) }}" class="rounded-full px-3 py-1 text-xs {{ $period === 'week' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600' }}">Mingguan</a>
    <a href="{{ request()->fullUrlWithQuery(['period' => 'month', 'from' => null, 'to' => null]) }}" class="rounded-full px-3 py-1 text-xs {{ $period === 'month' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600' }}">Bulanan</a>
</div>
