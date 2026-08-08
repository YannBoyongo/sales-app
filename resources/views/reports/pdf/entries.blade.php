@extends('reports.pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Produit</th>
                <th>Date</th>
                <th>Emplacement</th>
                <th class="num">Quantité</th>
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
                    <td>{{ $row->locations ?: '-' }}</td>
                    <td class="num">{{ number_format((int) $row->total_quantity, 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center muted">Aucune entrée enregistrée pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="4">Total</th>
                    <td class="num">{{ number_format($summaryQuantity, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
