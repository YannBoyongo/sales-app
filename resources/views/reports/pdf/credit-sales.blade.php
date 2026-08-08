@extends('reports.pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Produit</th>
                <th>Date</th>
                <th>Échéance</th>
                <th>Client(s)</th>
                <th class="num">Quantité</th>
                <th class="num">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        {{ $row->product_name }}
                        @if ($row->product_sku)
                            <br><span class="sku">{{ $row->product_sku }}</span>
                        @endif
                    </td>
                    <td>{{ \App\Http\Controllers\EntryListReportController::formatReportDate($row->movement_date) }}</td>
                    <td>{{ $row->due_dates ?: '-' }}</td>
                    <td>{{ $row->clients ?: '-' }}</td>
                    <td class="num">{{ number_format((int) $row->total_quantity, 0, ',', ' ') }}</td>
                    <td class="num">{{ \App\Support\Money::usd($row->total_amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center muted">Aucune vente crédit pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="5">Total</th>
                    <td class="num">{{ number_format($summaryQuantity, 0, ',', ' ') }}</td>
                    <td class="num">{{ \App\Support\Money::usd($summaryAmount) }}</td>
                </tr>
                <tr>
                    <th colspan="6">Solde crédit restant (ventes de la période)</th>
                    <td class="num">{{ \App\Support\Money::usd($summaryRemaining) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
