<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $site = $site ?? config('site');
        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($site['maps_query']);
        $ogImage = url($site['og_image'] ?? '/images/menu/xiway-menu-board.jpg');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'XIWAY COFFEE | Specialty Coffee & Cafe di Cimahi')</title>
    <meta name="description" content="@yield('meta_description', 'XIWAY COFFEE adalah coffee shop di Cimahi Utara yang menyajikan Specialty Arabika Gayo, coffee, non-coffee, food, dan ruang nyaman untuk WFH, meeting, dan nongkrong.')">
    <link rel="canonical" href="@yield('canonical', url('/'))">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="XIWAY COFFEE">
    <meta property="og:title" content="@yield('og_title', 'XIWAY COFFEE | Specialty Coffee & Cafe di Cimahi')">
    <meta property="og:description" content="@yield('og_description', 'Specialty Arabika Gayo, coffee, food, dan ruang nyaman untuk WFH, meeting, dan hangout di Citeureup, Cimahi Utara.')">
    <meta property="og:url" content="@yield('canonical', url('/'))">
    <meta property="og:image" content="@yield('og_image', $ogImage)">
    <meta property="og:locale" content="id_ID">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'XIWAY COFFEE | Specialty Coffee & Cafe di Cimahi')">
    <meta name="twitter:description" content="@yield('og_description', 'Specialty Arabika Gayo coffee shop di Cimahi Utara.')">
    <meta name="twitter:image" content="@yield('og_image', $ogImage)">

    @stack('head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cormorant-garamond:400,400i,500,600|source-sans-3:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body">
    @yield('content')

    <nav class="site-mobile-cta lg:hidden" aria-label="Quick actions">
        <a href="#menu">Menu</a>
        <a href="#lokasi">Maps</a>
        <a href="#vip">VIP</a>
    </nav>
    @stack('scripts')
</body>
</html>
