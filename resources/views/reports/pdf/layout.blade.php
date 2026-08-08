<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        .meta { color: #555; font-size: 9px; margin: 0 0 14px; }
        .summary { margin: 0 0 14px; }
        .summary p { margin: 0 0 4px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #1e3a5f; color: #fff; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        tfoot td, tfoot th { background: #f1f5f9; font-weight: bold; }
        .muted { color: #666; font-size: 8px; }
        .sku { color: #666; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">Période : {{ $period }} · Généré le {{ now()->format('d/m/Y H:i') }}</p>
    @isset($summaryHtml)
        <div class="summary">{!! $summaryHtml !!}</div>
    @endisset
    @yield('content')
</body>
</html>
