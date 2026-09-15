@props(['value' => 'gray'])
@php
    $map = [
        'green' => 'bg-[#e8fadf] text-[#28c76f]',
        'orange' => 'bg-[#fff3e8] text-[#ff9f43]',
        'blue' => 'bg-[#e0f9fc] text-[#00cfe8]',
        'red' => 'bg-[#fce5e6] text-[#ea5455]',
        'gray' => 'bg-slate-100 text-[#a8aaae]',
        'indigo' => 'bg-brand-soft text-brand',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($map[$value] ?? $map['gray'])]) }}>{{ $slot }}</span>
