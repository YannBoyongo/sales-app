<?php

namespace App\Services;

use App\Models\AccountingTransaction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;

class AccountingJournal
{
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
