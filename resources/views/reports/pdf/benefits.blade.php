@extends('reports.pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Vente</th>
                <th>Produit</th>
                <th>Lot</th>
                <th class="num">Qté</th>
                <th class="num">P.U. vente</th>
                <th class="num">P.U. coût</th>
                <th class="num">Vente</th>
                <th class="num">Coût</th>
                <th class="num">Bénéfice</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                @php
                    $revenue = (float) $item->line_total - (float) ($item->discount_amount ?? 0);
                @endphp
                <tr>
                    <td>{{ $item->sale?->sold_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>
                        {{ $item->sale->reference ?? '#'.$item->sale_id }}
                        @if ($item->branch)
                            <br><span class="muted">{{ $item->branch->name }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $item->product?->name ?? '-' }}
                        @if ($item->product?->sku)
                            <br><span class="sku">{{ $item->product->sku }}</span>
                        @endif
                    </td>
                    <td>{{ $item->batch_number ?: '-' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ \App\Support\Money::usd($item->unit_price) }}</td>
                    <td class="num">{{ $item->unit_cost !== null ? \App\Support\Money::usd($item->unit_cost) : '-' }}</td>
                    <td class="num">{{ \App\Support\Money::usd($revenue) }}</td>
                    <td class="num">{{ $item->cost_total !== null ? \App\Support\Money::usd($item->cost_total) : '-' }}</td>
                    <td class="num">{{ $item->benefit !== null ? \App\Support\Money::usd($item->benefit) : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center muted">Aucune vente pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
