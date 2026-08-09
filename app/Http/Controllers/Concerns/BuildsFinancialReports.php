<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait BuildsFinancialReports
{
    protected function authorizeFinancialReport(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasApplicationAdminAccess() || $user?->canAccessCashDeskFinanceFeatures() || $user?->canAccessPosSales(),
            403
        );
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    protected function resolveReportDateFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return [
            'date_from' => $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $filters['date_to'] ?? now()->toDateString(),
        ];
    }

    protected static function formatReportDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse($value)->translatedFormat('d/m/Y');
    }
}
