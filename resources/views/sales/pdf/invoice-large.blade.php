<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $sale->reference }}</title>
    <style>
        @page { margin: 36px 42px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            color: #1e293b;
            line-height: 1.45;
            margin: 0;
        }
        .accent-bar {
            height: 5px;
            background: #0f766e;
            margin: 0 0 22px 0;
            border-radius: 2px;
        }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .header-table td { vertical-align: top; padding: 0; }
        .logo-wrap {
            width: 120px;
            padding-right: 16px;
        }
        .logo-wrap img {
            max-width: 112px;
            max-height: 72px;
            display: block;
        }
        .shop-name {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin: 0 0 6px 0;
        }
        .shop-meta {
            font-size: 9pt;
            color: #475569;
            margin: 0;
        }
        .shop-meta strong { color: #334155; }
        .legal-row {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        .legal-row span { margin-right: 14px; white-space: nowrap; }
        .doc-title-row { width: 100%; margin-bottom: 22px; }
        .doc-title-row td { vertical-align: bottom; }
        .pill {
            display: inline-block;
            background: #f0fdfa;
            color: #0f766e;
            border: 1px solid #99f6e4;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 4px 10px;
            border-radius: 4px;
        }
        .doc-h1 {
            font-size: 22pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.03em;
        }
        .ref-block {
            text-align: right;
            font-size: 9pt;
            color: #64748b;
        }
        .ref-block .ref-val {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            display: block;
            margin-top: 4px;
        }
        .two-col { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .two-col td { width: 50%; vertical-align: top; padding: 0 12px 0 0; }
        .two-col td:last-child { padding: 0 0 0 12px; }
        .box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 16px;
            background: #f8fafc;
        }
        .box h3 {
            margin: 0 0 10px 0;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            font-weight: bold;
        }
        .box p { margin: 0 0 4px 0; font-size: 9.5pt; color: #334155; }
        .box .muted { color: #94a3b8; font-size: 8.5pt; }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.lines thead th {
            background: #0f172a;
            color: #f8fafc;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: bold;
            padding: 10px 12px;
            text-align: left;
        }
        table.lines thead th.r { text-align: right; }
        table.lines tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5pt;
            vertical-align: top;
        }
        table.lines tbody tr:nth-child(even) td { background: #f8fafc; }
        table.lines tbody td.r { text-align: right; }
        .totals-wrap { margin-top: 20px; width: 100%; }
        .totals-wrap td { vertical-align: top; }
        .total-due {
            width: 280px;
            margin-left: auto;
            border: 2px solid #0f766e;
            border-radius: 8px;
            overflow: hidden;
        }
        .total-due .label {
            background: #0f766e;
            color: #fff;
            padding: 10px 16px;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .total-due .amount {
            padding: 14px 16px;
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            background: #fff;
        }
        .footer {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5pt;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="accent-bar"></div>

    <table class="header-table">
        <tr>
            <td class="logo-wrap">
                @if (!empty($logoDataUrl))
                    <img src="{{ $logoDataUrl }}" alt="">
                @endif
            </td>
            <td>
                <p class="shop-name">{{ $setting?->shopname ?? config('app.name', 'Commerce') }}</p>
                @if ($setting && ($setting->address || $setting->phone || $setting->email))
                    <p class="shop-meta">
                        @if ($setting->address)
                            <strong>Adresse</strong> — {{ $setting->address }}<br>
                        @endif
                        @if ($setting->phone)
                            <strong>Tél.</strong> {{ $setting->phone }}
                        @endif
                        @if ($setting->phone && $setting->email) &nbsp;·&nbsp; @endif
                        @if ($setting->email)
                            <strong>Email</strong> {{ $setting->email }}
                        @endif
                    </p>
                @endif
                @if ($setting && ($setting->rccm || $setting->idnat || $setting->nif))
                    <div class="legal-row">
                        @if ($setting->rccm)<span><strong>RCCM</strong> {{ $setting->rccm }}</span>@endif
                        @if ($setting->idnat)<span><strong>ID NAT</strong> {{ $setting->idnat }}</span>@endif
                        @if ($setting->nif)<span><strong>NIF</strong> {{ $setting->nif }}</span>@endif
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="doc-title-row">
        <tr>
            <td>
                <span class="pill">Document commercial</span>
                <h1 class="doc-h1" style="margin-top: 10px;">Facture</h1>
            </td>
            <td class="ref-block">
                Référence
                <span class="ref-val">{{ $sale->reference }}</span>
                Date : {{ $sale->sold_at->translatedFormat('d/m/Y à H:i') }}
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="box">
                    <h3>Facturé à</h3>
                    <p><strong>{{ $sale->client?->name ?? 'Client au comptant' }}</strong></p>
                    @if ($sale->client?->phone)
                        <p class="muted">Tél. {{ $sale->client->phone }}</p>
                    @endif
                    @if (!$sale->client)
                        <p class="muted">Vente sans fiche client enregistrée.</p>
                    @endif
                </div>
            </td>
            <td>
                <div class="box">
                    <h3>Détails</h3>
                    <p><strong>Session</strong> #{{ $sale->session->id }}</p>
                    <p><strong>Branche</strong> {{ $sale->session->branch->name }}</p>
                    <p><strong>Paiement</strong> {{ $sale->payment_type === 'credit' ? 'Crédit' : 'Cash' }}</p>
                    <p><strong>Vendeur</strong> {{ $sale->user->name }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Produit</th>
                <th>Emplacement</th>
                <th class="r">Qté</th>
                <th class="r">Prix unit.</th>
                <th class="r">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>
                        {{ $item->product->name }}
                        @if (!empty($item->reference))
                            <br><span style="font-size: 8pt; color: #64748b;">Réf. {{ $item->reference }}</span>
                        @endif
                    </td>
                    <td>{{ $item->location->name }}</td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r">{{ \App\Support\Money::usd($item->unit_price) }}</td>
                    <td class="r"><strong>{{ \App\Support\Money::usd($item->line_total) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width: 300px;">
                <div style="margin-bottom: 8px; font-size: 10pt;">
                    <div style="display:flex; justify-content:space-between;"><span>Sous-total</span><span>{{ \App\Support\Money::usd($sale->subtotal_amount ?? $sale->total_amount) }}</span></div>
                    @if ($sale->sale_status === \App\Models\Sale::STATUS_PENDING_DISCOUNT && $sale->discount_requested_amount)
                        <div style="display:flex; justify-content:space-between; margin-top:4px; color:#92400e;"><span>Remise demandée (en attente)</span><span>− {{ \App\Support\Money::usd($sale->discount_requested_amount) }}</span></div>
                        <div style="margin-top:6px; font-size:9pt; color:#64748b;">Si approuvée : {{ \App\Support\Money::usd(max(0, (float) ($sale->subtotal_amount ?? 0) - (float) ($sale->discount_requested_amount ?? 0))) }}</div>
                    @elseif ($sale->discount_amount && (float) $sale->discount_amount > 0)
                        <div style="display:flex; justify-content:space-between; margin-top:4px;"><span>Remise</span><span>− {{ \App\Support\Money::usd($sale->discount_amount) }}</span></div>
                    @endif
                </div>
                <div class="total-due">
                    <div class="label">Total à payer</div>
                    <div class="amount">{{ \App\Support\Money::usd($sale->total_amount) }}</div>
                </div>
                @if ($sale->sale_status === \App\Models\Sale::STATUS_PENDING_DISCOUNT)
                    <p style="margin-top:8px; font-size:9pt; color:#92400e;">Remise non encore approuvée — montant ci-dessus = sous-total enregistré.</p>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        Merci pour votre achat. — {{ $setting?->shopname ?? config('app.name') }}
    </div>
</body>
</html>
