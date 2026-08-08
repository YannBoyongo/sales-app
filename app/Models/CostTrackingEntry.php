<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostTrackingEntry extends Model
{
    public const DIRECTION_ENTRY = 'entry';

    public const DIRECTION_EXIT = 'exit';

    protected $fillable = [
        'occurred_on',
        'direction',
        'cost_center_id',
        'cost_transaction_type_id',
        'amount',
        'description',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(CostTransactionType::class, 'cost_transaction_type_id');
    }

    public function isEntry(): bool
    {
        return $this->direction === self::DIRECTION_ENTRY;
    }
}
