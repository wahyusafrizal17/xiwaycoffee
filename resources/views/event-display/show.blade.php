<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Event · XIWAY COFFEE</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #0b0b0b;
        }
        .stage {
            position: relative;
            width: 100vw;
            height: 100vh;
        }
        .stage img {
            position: absolute;
            inset: 0;
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #0b0b0b;
            opacity: 0;
            transition: opacity 0.8s ease;
        }
        .stage img.on { opacity: 1; }
    </style>
</head>
<body>
    <div class="stage">
        @foreach ($imageUrls as $index => $url)
            <img src="{{ $url }}" alt="Event XIWAY Coffee" @class(['on' => $index === 0])>
        @endforeach
    </div>
    @if (count($imageUrls) > 1)
        <script>
            const slides = [...document.querySelectorAll('.stage img')];
            let current = 0;
            setInterval(() => {
                slides[current].classList.remove('on');
                current = (current + 1) % slides.length;
                slides[current].classList.add('on');
            }, 8000);
        </script>
    @endif
</body>
</html>
