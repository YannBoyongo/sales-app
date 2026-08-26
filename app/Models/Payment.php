<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'amount',
        'paid_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cautionUsage(): HasOne
    {
        return $this->hasOne(ClientCautionUsage::class);
    }

    public function paidWithCaution(): bool
    {
        if ($this->relationLoaded('cautionUsage')) {
            return $this->cautionUsage !== null;
        }

        return $this->cautionUsage()->exists();
    }
}
