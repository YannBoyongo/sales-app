<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
    ];

    public function creditSales(): HasMany
    {
        return $this->hasMany(SaleItem::class)->where('payment_type', 'credit');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function totalCreditAmount(): string
    {
        return (string) $this->creditSales()->sum('line_total');
    }

    public function totalPaidAmount(): string
    {
        return (string) $this->payments()->sum('amount');
    }

    public function debtBalance(): string
    {
        return bcsub($this->totalCreditAmount(), $this->totalPaidAmount(), 2);
    }
}
