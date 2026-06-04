<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    protected $fillable = [
        'pos_terminal_id',
        'opened_by',
        'session_date',
        'closed_by',
        'opened_at',
        'closed_at',
        'closed_at_preserved',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'closed_at_preserved' => 'datetime',
        ];
    }

    public function effectiveSessionDate(): Carbon
    {
        if ($this->session_date !== null) {
            return $this->session_date->copy()->startOfDay();
        }

        return ($this->opened_at ?? now())->copy()->startOfDay();
    }

    public function alignSalesSoldAtToSessionDate(): void
    {
        $sessionDate = $this->effectiveSessionDate()->toDateString();
        $target = $this->effectiveSessionDate();

        $this->sales()
            ->where(function ($query) use ($sessionDate) {
                $query->whereRaw('DATE(sold_at) != ?', [$sessionDate])
                    ->orWhereNull('sold_at');
            })
            ->update(['sold_at' => $target]);
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function effectiveClosedAt(): Carbon
    {
        if ($this->closed_at !== null) {
            return $this->closed_at->copy();
        }

        if ($this->closed_at_preserved !== null) {
            return $this->closed_at_preserved->copy();
        }

        return $this->effectiveSessionDate()->copy()->endOfDay();
    }

    public function canBeReopened(): bool
    {
        return $this->closed_at !== null;
    }
}
