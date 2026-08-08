<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

trait RendersReportPdf
{
    protected function streamReportPdf(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper('a4', $orientation);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename) ?: 'rapport';

        return $pdf->stream($safe.'.pdf');
    }

    protected function formatReportPeriod(string $dateFrom, string $dateTo): string
    {
        return Carbon::parse($dateFrom)->translatedFormat('d/m/Y')
            .' - '
            .Carbon::parse($dateTo)->translatedFormat('d/m/Y');
    }
}
