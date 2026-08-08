<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    protected $fillable = ['name'];

    public function entries(): HasMany
    {
        return $this->hasMany(CostTrackingEntry::class);
    }
}
