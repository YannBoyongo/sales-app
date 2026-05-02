<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $sale->reference }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        * { box-sizing: border-box; }
        html {
            background: #d4d4d4;
        }
        body {
            font-family: ui-monospace, 'Cascadia Code', 'Segoe UI Mono', Consolas, 'Courier New', monospace;
            width: 80mm;
            max-width: 100vw;
            margin: 0 auto;
            padding: 3mm 4mm;
            color: #000;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.35;
            -webkit-font-smoothing: antialiased;
            background: #fff;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .row {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 700;
            gap: 6px;
        }
        .row > span {
            min-width: 0;
        }
        .row > span:last-child {
            flex-shrink: 0;
        }
        .small { font-size: 15px; font-weight: 700; }
        .header-shop {
            font-size: 18px;
            font-weight: 700;
            margin: 4px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .header-meta { margin: 2px 0; }
        .header-legal {
            font-size: 15px;
            font-weight: 700;
            margin-top: 8px;
            line-height: 1.4;
        }
        .logo {
            max-width: 100%;
            max-height: 14mm;
            object-fit: contain;
            margin: 0 auto 6px;
            display: block;
        }
        .ticket-meta { font-size: 16px; font-weight: 700; }
        @media print {
            html { background: #fff; }
            .noprint { display: none; }
            body {
                width: 80mm;
                max-width: none;
                margin: 0;
                padding: 3mm 4mm;
            }
        }
    </style>
</head>
<body>
    <button class="noprint" onclick="window.print()">Imprimer</button>

    <div class="center">
        @if (!empty($logoDataUrl))
            <img src="{{ $logoDataUrl }}" alt="" class="logo">
        @endif
        <div class="header-shop">{{ $setting?->shopname ?? config('app.name') }}</div>
        @if ($setting?->address)
            <div class="header-meta">{{ $setting->address }}</div>
        @endif
        @if ($setting?->phone || $setting?->email)
            <div class="header-meta">
                @if ($setting?->phone)Tél. {{ $setting->phone }}@endif
                @if ($setting?->phone && $setting?->email) · @endif
                @if ($setting?->email){{ $setting->email }}@endif
            </div>
        @endif
        @if ($setting && ($setting->rccm || $setting->idnat || $setting->nif))
            <div class="header-legal">
                @if ($setting->rccm)RCCM {{ $setting->rccm }}@endif
                @if ($setting->rccm && ($setting->idnat || $setting->nif))<br>@endif
                @if ($setting->idnat)ID NAT {{ $setting->idnat }}@endif
                @if ($setting->idnat && $setting->nif) · @endif
                @if ($setting->nif)NIF {{ $setting->nif }}@endif
            </div>
        @endif
    </div>

    <div class="line"></div>

    <div class="center ticket-meta">
        Ticket {{ $sale->reference }}<br>
        {{ $sale->sold_at->translatedFormat('d/m/Y H:i') }}<br>
        {{ $sale->session->branch->name }}
    </div>

    <div class="line"></div>

    @foreach ($sale->items as $item)
        <div class="small" style="margin-top: 6px;">{{ $item->product->name }}</div>
        <div class="row">
            <span>{{ $item->quantity }} × {{ \App\Support\Money::usd($item->unit_price) }}</span>
            <span>{{ \App\Support\Money::usd($item->line_total) }}</span>
        </div>
    @endforeach

    <div class="line"></div>
    <div class="row small"><span>Sous-total</span><span>{{ \App\Support\Money::usd($sale->subtotal_amount ?? $sale->total_amount) }}</span></div>
    @if (($sale->sale_status ?? '') === \App\Models\Sale::STATUS_PENDING_DISCOUNT && $sale->discount_requested_amount)
        <div class="row small"><span>Remise (attente)</span><span>-{{ \App\Support\Money::usd($sale->discount_requested_amount) }}</span></div>
        <div class="row small"><span>Si approuvée</span><span>{{ \App\Support\Money::usd(max(0, (float) ($sale->subtotal_amount ?? 0) - (float) ($sale->discount_requested_amount ?? 0))) }}</span></div>
    @elseif ($sale->discount_amount && (float) $sale->discount_amount > 0)
        <div class="row small"><span>Remise</span><span>-{{ \App\Support\Money::usd($sale->discount_amount) }}</span></div>
    @endif
    <div class="line"></div>
    <div class="row"><span>TOTAL</span><span>{{ \App\Support\Money::usd($sale->total_amount) }}</span></div>
    <div class="row small"><span>Paiement</span><span>{{ $sale->payment_type === 'credit' ? 'Crédit' : 'Cash' }}</span></div>
    <div class="row small"><span>Client</span><span>{{ $sale->client?->name ?? '—' }}</span></div>
    <div class="row small"><span>Tél.</span><span>{{ $sale->client?->phone ?? '—' }}</span></div>

    <div class="line"></div>
    <div class="center small">Merci et à bientôt</div>
</body>
</html>
