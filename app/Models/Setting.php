<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'shopname',
        'phone',
        'email',
        'address',
        'rccm',
        'idnat',
        'nif',
        'logo',
    ];
}
