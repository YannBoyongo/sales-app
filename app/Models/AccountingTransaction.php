<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'pos_terminal_id',
        'transaction_date',
        'reference',
        'amount',
        'entry_type',
        'account_code',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }
}
