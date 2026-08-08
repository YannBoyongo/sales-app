<?php

namespace App\Services;

use App\Models\CostTrackingEntry;

class CostTrackingBalanceService
{
    public function recalculateAll(): void
    {
        $balance = '0.00';

        CostTrackingEntry::query()
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->each(function (CostTrackingEntry $entry) use (&$balance) {
                $amount = number_format((float) $entry->amount, 2, '.', '');

                if ($entry->isEntry()) {
                    $balance = bcadd($balance, $amount, 2);
                } else {
                    $balance = bcsub($balance, $amount, 2);
                }

                if ((string) $entry->balance_after !== $balance) {
                    $entry->update(['balance_after' => $balance]);
                }
            });
    }

    public function currentBalance(): string
    {
        $latest = CostTrackingEntry::query()
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->value('balance_after');

        return number_format((float) ($latest ?? 0), 2, '.', '');
    }
}
