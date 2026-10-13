<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanda tangan kontrak</title>
    <style>
        body { margin: 0; background: #f6f3ef; color: #111; font-family: Arial, Helvetica, sans-serif; }
        main { max-width: 420px; margin: 48px auto; background: #fff; padding: 28px 24px; }
        h1 { margin: 0 0 12px; font-size: 20px; }
        p { margin: 4px 0; }
    </style>
</head>
<body>
    <main>
        <h1>Tanda tangan sah</h1>
        <p>{{ $employee->user?->name }}</p>
        <p>{{ $employee->position }}</p>
        <p>Ditandatangani {{ $employee->contract_signed_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y H:i') }} WIB</p>
    </main>
</body>
</html>
