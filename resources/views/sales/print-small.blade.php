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
    @php
        $baseAmount = (string) ($sale->subtotal_amount ?? $sale->total_amount ?? '0');
        $discountValue = $sale->isPendingDiscount()
            ? (string) ($sale->discount_requested_amount ?? '0')
            : (string) ($sale->discount_amount ?? '0');
        $finalAmount = $sale->expectedPayableAmount();
    @endphp
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
        {{ $sale->branch->name }}
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
    <div class="row small"><span>Montant à payer</span><span>{{ \App\Support\Money::usd($baseAmount) }}</span></div>
    @if (bccomp($discountValue, '0.00', 2) === 1)
        <div class="row small">
            <span>Remise{{ $sale->isPendingDiscount() ? ' (attente)' : '' }}</span>
            <span>-{{ \App\Support\Money::usd($discountValue) }}</span>
        </div>
    @endif
    <div class="line"></div>
    <div class="row"><span>NOUVEAU TOTAL</span><span>{{ \App\Support\Money::usd($finalAmount) }}</span></div>
    <div class="row small">
        <span>Statut paiement</span>
        <span>
            @if ($sale->payment_status === \App\Models\Sale::PAYMENT_STATUS_NOT_PAID)
                Non payé
            @elseif ($sale->payment_status === \App\Models\Sale::PAYMENT_STATUS_PARTIALLY_PAID)
                Partiellement payé
            @else
                Entièrement payé
            @endif
        </span>
    </div>
    <div class="row small"><span>Client</span><span>{{ $sale->displayClientName() ?? '—' }}</span></div>
    <div class="row small"><span>Tél.</span><span>{{ $sale->displayClientPhone() ?? '—' }}</span></div>

    <div class="line"></div>
    <div class="center small">Merci et à bientôt</div>
</body>
</html>
