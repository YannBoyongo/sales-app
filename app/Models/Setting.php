<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'field_pos_stock_branch_id',
        'field_pos_stock_location_id',
    ];

    protected function casts(): array
    {
        return [
            'field_pos_stock_branch_id' => 'integer',
            'field_pos_stock_location_id' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'shopname' => config('app.name', 'Sales App'),
                'phone' => '',
                'email' => '',
                'address' => '',
                'rccm' => '',
                'idnat' => '',
                'nif' => '',
            ]
        );
    }

    public function fieldPosStockBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'field_pos_stock_branch_id');
    }

    public function fieldPosStockLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'field_pos_stock_location_id');
    }

    public function hasFieldPosStockLocation(): bool
    {
        return $this->field_pos_stock_location_id !== null
            && $this->field_pos_stock_branch_id !== null;
    }

    public static function resolveFieldPosStockLocation(): ?Location
    {
        $setting = static::query()->first();

        if ($setting === null || ! $setting->hasFieldPosStockLocation()) {
            return null;
        }

        return Location::query()
            ->with('branch:id,name')
            ->find($setting->field_pos_stock_location_id);
    }
}
