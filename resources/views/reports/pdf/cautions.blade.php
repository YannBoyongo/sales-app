@extends('reports.pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th class="center">#</th>
                <th>Client</th>
                <th>Date</th>
                <th class="num">Dépôts</th>
                <th class="num">Utilisations</th>
                <th class="num">Net période</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                @php
                    $net = bcsub((string) $row->total_deposits, (string) $row->total_usages, 2);
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        {{ $row->name }}
                        @if ($row->phone)
                            <br><span class="muted">{{ $row->phone }}</span>
                        @endif
                    </td>
                    <td>{{ \App\Http\Controllers\EntryListReportController::formatReportDate($row->movement_date) }}</td>
                    <td class="num">{{ \App\Support\Money::usd($row->total_deposits) }}</td>
                    <td class="num">{{ \App\Support\Money::usd($row->total_usages) }}</td>
                    <td class="num">{{ \App\Support\Money::usd($net) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center muted">Aucune caution enregistrée pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <td class="num">{{ \App\Support\Money::usd($summaryDeposits) }}</td>
                    <td class="num">{{ \App\Support\Money::usd($summaryUsages) }}</td>
                    <td class="num">{{ \App\Support\Money::usd(bcsub((string) $summaryDeposits, (string) $summaryUsages, 2)) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
