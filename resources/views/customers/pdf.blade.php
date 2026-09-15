<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Pelanggan</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>Daftar Pelanggan</h1>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama</th>
                <th>No. HP</th>
                <th>Email</th>
                <th>Level</th>
                <th>Alamat</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $customer)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $customer->code }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->phone ?: '—' }}</td>
                    <td>{{ $customer->email ?: '—' }}</td>
                    <td>{{ $customer->membership_level?->label() }}</td>
                    <td>{{ $customer->address ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
