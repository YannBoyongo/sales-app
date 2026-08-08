@extends('reports.pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Produit</th>
                <th>Date</th>
                <th>Lieu</th>
                <th>Utilisateur</th>
                <th class="num">Qté</th>
                <th class="num">Prix</th>
                <th class="num">Remise</th>
                <th class="num">Payé</th>
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
                    <td>{{ $row->branch_name }}<br><span class="muted">{{ $row->location_name }}</span></td>
                    <td>{{ $row->user_name }}</td>
                    <td class="num">{{ number_format((int) $row->quantity, 0, ',', ' ') }}</td>
                    <td class="num">{{ \App\Support\Money::usd($row->original_amount) }}</td>
                    <td class="num">{{ bccomp((string) $row->approved_discount, '0', 2) === 1 ? \App\Support\Money::usd($row->approved_discount) : '-' }}</td>
                    <td class="num">{{ \App\Support\Money::usd($row->amount_paid) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center muted">Aucune remise enregistrée pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="6">Total</th>
                    <td class="num">{{ \App\Support\Money::usd($summaryOriginal) }}</td>
                    <td class="num">{{ bccomp((string) $summaryApproved, '0', 2) === 1 ? \App\Support\Money::usd($summaryApproved) : '-' }}</td>
                    <td class="num">{{ \App\Support\Money::usd($summaryPaid) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
