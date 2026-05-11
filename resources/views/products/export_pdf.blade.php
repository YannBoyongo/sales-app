<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .num { text-align: right; }
        .muted { color: #666; font-size: 10px; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Liste des produits</h1>
    <p class="muted">Export du {{ now()->format('d/m/Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Code</th>
                <th>Département</th>
                <th class="num">Seuil min.</th>
                <th class="num">Prix unitaire (USD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku ?? '—' }}</td>
                    <td>{{ $product->department->name ?? '—' }}</td>
                    <td class="num">{{ $product->minimum_stock ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $product->unit_price, 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
