<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F4F1EA">
    <title>Undangan · {{ $guest->name }} · XIWAY COFFEE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #F4F1EA;
            --ink: #1a1a1a;
            --brown: #6B4E3D;
            --muted: #6b6b6b;
            --line: #cfc8bc;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #e8e2d8;
            font-family: 'Montserrat', system-ui, sans-serif;
            color: var(--ink);
            display: flex;
            justify-content: center;
            padding: 1rem;
        }

        .card {
            width: min(100%, 420px);
            min-height: min(100vh - 2rem, 760px);
            background: var(--cream);
            position: relative;
            overflow: hidden;
            padding: 1.35rem 1.5rem 2rem;
            box-shadow: 0 18px 50px rgba(0,0,0,.12);
            animation: fade .7s ease both;
        }

        @keyframes fade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: none; }
        }

        .top {
            display: grid;
            grid-template-columns: 3.2rem 1fr 3.2rem;
            align-items: start;
            gap: .5rem;
            margin-bottom: 1.75rem;
        }

        .top-spacer { width: 100%; }

        .brand {
            text-align: center;
            line-height: 1;
        }

        .brand-xiway {
            display: block;
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: .18em;
        }

        .brand-coffee {
            display: block;
            margin-top: .2rem;
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .42em;
            padding-left: .42em;
        }

        .side-tag {
            text-align: right;
            font-size: .42rem;
            font-weight: 600;
            letter-spacing: .12em;
            line-height: 1.45;
            color: var(--ink);
            padding-top: .1rem;
        }

        .title-block {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .undangan {
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .35em;
            color: #b0a89c;
            padding-left: .35em;
        }

        .grand {
            margin-top: .15rem;
            font-family: 'Great Vibes', cursive;
            font-size: clamp(2.6rem, 11vw, 3.4rem);
            color: var(--brown);
            line-height: 1.1;
            font-weight: 400;
        }

        .to {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .to-label {
            font-size: .78rem;
            color: var(--muted);
            margin-bottom: .35rem;
        }

        .to-name {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .copy {
            text-align: center;
            font-size: .78rem;
            line-height: 1.65;
            color: var(--muted);
            max-width: 20rem;
            margin: 0 auto 1.25rem;
        }

        .details {
            display: grid;
            gap: .7rem;
            max-width: 16rem;
            margin: 0 auto 1.25rem;
        }

        .row {
            display: grid;
            grid-template-columns: 1.35rem 1fr;
            gap: .65rem;
            align-items: center;
            font-size: .8rem;
            color: var(--ink);
        }

        .row svg {
            width: 1.1rem;
            height: 1.1rem;
            stroke: var(--brown);
            fill: none;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .row a {
            color: inherit;
            text-decoration: none;
        }

        .row a:hover { text-decoration: underline; color: var(--brown); }

        .honor {
            text-align: center;
            font-size: .78rem;
            line-height: 1.65;
            color: var(--muted);
            max-width: 18rem;
            margin: 0 auto 1.5rem;
        }

        .footer-line {
            width: 4.5rem;
            height: 1px;
            background: var(--ink);
            margin: 0 auto .45rem;
        }

        .see-you {
            font-size: .58rem;
            font-weight: 600;
            letter-spacing: .28em;
            padding-left: .28em;
            text-align: center;
        }

        .see-you a {
            color: inherit;
            text-decoration: none;
        }

        .see-you a:hover {
            color: var(--brown);
            text-decoration: underline;
        }

        .note {
            margin-top: 1.1rem;
            font-size: .68rem;
            line-height: 1.5;
            color: var(--brown);
            max-width: 100%;
            text-align: center;
        }

        .maps-cta {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .4rem;
            text-decoration: none;
            color: var(--ink);
        }

        .maps-cta svg {
            width: 1.75rem;
            height: 1.75rem;
        }

        .maps-cta span {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .04em;
        }

        .maps-cta:hover {
            color: var(--brown);
        }

        .maps-cta:hover span {
            text-decoration: underline;
        }

        .watermark {
            position: absolute;
            inset: 0;
            margin: auto;
            width: min(72%, 280px);
            height: auto;
            opacity: .08;
            pointer-events: none;
            user-select: none;
            z-index: 0;
        }

        .card > *:not(.watermark) {
            position: relative;
            z-index: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            .card { animation: none; }
        }
    </style>
</head>
<body>
    <article class="card">
        <img class="watermark" src="{{ asset('images/logo/xiway-logo.png') }}" alt="" aria-hidden="true">
        <header class="top">
            <div class="top-spacer" aria-hidden="true"></div>
            <div class="brand">
                <span class="brand-xiway">XIWAY</span>
                <span class="brand-coffee">COFFEE</span>
            </div>
            <div class="side-tag">GOOD<br>COFFEE<br>BETTER<br>PEOPLE</div>
        </header>

        <div class="title-block">
            <div class="undangan">UNDANGAN</div>
            <h1 class="grand">Grand Opening</h1>
        </div>

        <div class="to">
            <p class="to-label">Kepada Yth.</p>
            <p class="to-name">{{ $guest->name }}</p>
        </div>

        <p class="copy">
            Dengan hormat, Kami mengundang Bapak/Ibu untuk hadir dalam acara Grand Opening XIWAY COFFEE yang akan diselenggarakan pada:
        </p>

        <div class="details">
            <div class="row">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                <span>Minggu, 20 September 2026</span>
            </div>
            <div class="row">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span>Pukul 11.00 – Selesai</span>
            </div>
            <div class="row">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener">XIWAY COFFEE</a>
            </div>
        </div>

        <p class="honor">Merupakan suatu kehormatan bagi kami atas kehadiran Bapak/Ibu.</p>

        <div class="footer-line"></div>
        <div class="see-you">
            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener">SEE YOU AT XIWAY</a>
        </div>
        <p class="note"><strong>NOTE:</strong> Mohon dukungan ulasan positif di map</p>

        <a class="maps-cta" href="{{ $mapsUrl }}" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#EA4335" d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7z"/>
                <circle fill="#fff" cx="12" cy="9" r="2.5"/>
            </svg>
            <span>XIWAY COFFEE, Cimahi</span>
        </a>
    </article>
</body>
</html>
