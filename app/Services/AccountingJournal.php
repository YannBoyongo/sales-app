<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SalesSession;
use App\Models\User;

class AccountingJournal
{
    public static function recordSessionClosure(SalesSession $session, User $user): void
    {
        $session->loadMissing('branch');

        $cash = (string) $session->cashSalesTotal();
        $expenses = (string) $session->expensesTotal();

        $date = $session->closed_at?->toDateString() ?? now()->toDateString();
        $branch = $session->branch?->name ?? '—';

        if (bccomp($cash, '0', 2) === 1) {
            AccountingTransaction::create([
                'user_id' => $user->id,
                'transaction_date' => $date,
                'reference' => mb_substr(sprintf(
                    'Clôture session #%d — encaissements cash (%s)',
                    $session->id,
                    $branch
                ), 0, 255),
                'amount' => $cash,
                'entry_type' => 'debit',
            ]);
        }

        if (bccomp($expenses, '0', 2) === 1) {
            AccountingTransaction::create([
                'user_id' => $user->id,
                'transaction_date' => $date,
                'reference' => mb_substr(sprintf(
                    'Clôture session #%d — dépenses (%s)',
                    $session->id,
                    $branch
                ), 0, 255),
                'amount' => $expenses,
                'entry_type' => 'credit',
            ]);
        }
    }

    public static function recordClientPayment(Payment $payment, Client $client, User $user): void
    {
        $date = $payment->paid_at?->toDateString() ?? now()->toDateString();

        AccountingTransaction::create([
            'user_id' => $user->id,
            'transaction_date' => $date,
            'reference' => mb_substr(sprintf(
                'Paiement dette client : %s (paiement #%d)%s',
                $client->name,
                $payment->id,
                $payment->note ? ' — '.$payment->note : ''
            ), 0, 255),
            'amount' => (string) $payment->amount,
            'entry_type' => 'debit',
        ]);
    }
}
