<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsFinancialReports;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CautionListReportController extends Controller
{
    use BuildsFinancialReports;
    use RendersReportPdf;

    public function __invoke(Request $request): View
    {
        $this->authorizeFinancialReport();
        $filters = $this->resolveReportDateFilters($request);

        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $depositSub = DB::table('client_caution_deposits')
            ->select([
                'client_id',
                DB::raw('SUM(amount) as total_deposits'),
                DB::raw('MAX(deposited_at) as last_deposit_at'),
            ])
            ->whereDate('deposited_at', '>=', $dateFrom)
            ->whereDate('deposited_at', '<=', $dateTo)
            ->groupBy('client_id');

        $usageSub = DB::table('client_caution_usages')
            ->select([
                'client_id',
                DB::raw('SUM(amount) as total_usages'),
                DB::raw('MAX(used_at) as last_usage_at'),
            ])
            ->whereDate('used_at', '>=', $dateFrom)
            ->whereDate('used_at', '<=', $dateTo)
            ->groupBy('client_id');

        $summaryDeposits = (float) DB::table('client_caution_deposits')
            ->whereDate('deposited_at', '>=', $dateFrom)
            ->whereDate('deposited_at', '<=', $dateTo)
            ->sum('amount');

        $summaryUsages = (float) DB::table('client_caution_usages')
            ->whereDate('used_at', '>=', $dateFrom)
            ->whereDate('used_at', '<=', $dateTo)
            ->sum('amount');

        $rows = Client::query()
            ->select([
                'clients.id',
                'clients.name',
                'clients.phone',
                DB::raw('COALESCE(deposits.total_deposits, 0) as total_deposits'),
                DB::raw('COALESCE(usages.total_usages, 0) as total_usages'),
                DB::raw('CASE
                    WHEN deposits.last_deposit_at IS NULL THEN usages.last_usage_at
                    WHEN usages.last_usage_at IS NULL THEN deposits.last_deposit_at
                    ELSE GREATEST(deposits.last_deposit_at, usages.last_usage_at)
                END as movement_date'),
            ])
            ->leftJoinSub($depositSub, 'deposits', 'deposits.client_id', '=', 'clients.id')
            ->leftJoinSub($usageSub, 'usages', 'usages.client_id', '=', 'clients.id')
            ->where(function ($query) {
                $query->whereRaw('COALESCE(deposits.total_deposits, 0) > 0')
                    ->orWhereRaw('COALESCE(usages.total_usages, 0) > 0');
            })
            ->orderBy('clients.name')
            ->paginate(50)
            ->withQueryString();

        return view('reports.cautions', compact('rows', 'filters', 'summaryDeposits', 'summaryUsages'));
    }

    public function pdf(Request $request): Response
    {
        $data = $this->buildCautionReportData($request);

        return $this->streamReportPdf('reports.pdf.cautions', [
            'title' => 'Liste de cautions',
            'period' => $this->formatReportPeriod($data['filters']['date_from'], $data['filters']['date_to']),
            'rows' => $data['rows'],
            'summaryDeposits' => $data['summaryDeposits'],
            'summaryUsages' => $data['summaryUsages'],
        ], 'liste-cautions', 'landscape');
    }

    /**
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     rows: \Illuminate\Support\Collection,
     *     summaryDeposits: float,
     *     summaryUsages: float
     * }
     */
    private function buildCautionReportData(Request $request): array
    {
        $this->authorizeFinancialReport();
        $filters = $this->resolveReportDateFilters($request);

        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $depositSub = DB::table('client_caution_deposits')
            ->select([
                'client_id',
                DB::raw('SUM(amount) as total_deposits'),
                DB::raw('MAX(deposited_at) as last_deposit_at'),
            ])
            ->whereDate('deposited_at', '>=', $dateFrom)
            ->whereDate('deposited_at', '<=', $dateTo)
            ->groupBy('client_id');

        $usageSub = DB::table('client_caution_usages')
            ->select([
                'client_id',
                DB::raw('SUM(amount) as total_usages'),
                DB::raw('MAX(used_at) as last_usage_at'),
            ])
            ->whereDate('used_at', '>=', $dateFrom)
            ->whereDate('used_at', '<=', $dateTo)
            ->groupBy('client_id');

        $summaryDeposits = (float) DB::table('client_caution_deposits')
            ->whereDate('deposited_at', '>=', $dateFrom)
            ->whereDate('deposited_at', '<=', $dateTo)
            ->sum('amount');

        $summaryUsages = (float) DB::table('client_caution_usages')
            ->whereDate('used_at', '>=', $dateFrom)
            ->whereDate('used_at', '<=', $dateTo)
            ->sum('amount');

        $rows = Client::query()
            ->select([
                'clients.id',
                'clients.name',
                'clients.phone',
                DB::raw('COALESCE(deposits.total_deposits, 0) as total_deposits'),
                DB::raw('COALESCE(usages.total_usages, 0) as total_usages'),
                DB::raw('CASE
                    WHEN deposits.last_deposit_at IS NULL THEN usages.last_usage_at
                    WHEN usages.last_usage_at IS NULL THEN deposits.last_deposit_at
                    ELSE GREATEST(deposits.last_deposit_at, usages.last_usage_at)
                END as movement_date'),
            ])
            ->leftJoinSub($depositSub, 'deposits', 'deposits.client_id', '=', 'clients.id')
            ->leftJoinSub($usageSub, 'usages', 'usages.client_id', '=', 'clients.id')
            ->where(function ($query) {
                $query->whereRaw('COALESCE(deposits.total_deposits, 0) > 0')
                    ->orWhereRaw('COALESCE(usages.total_usages, 0) > 0');
            })
            ->orderBy('clients.name')
            ->get();

        return compact('filters', 'rows', 'summaryDeposits', 'summaryUsages');
    }
}
