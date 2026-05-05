<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    protected $fillable = [
        'branch_id',
        'location_id',
        'name',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(PosShift::class);
    }

    public function posUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pos_terminal_user')->withTimestamps();
    }

    public function openShift(): ?PosShift
    {
        return $this->shifts()->whereNull('closed_at')->first();
    }
}
