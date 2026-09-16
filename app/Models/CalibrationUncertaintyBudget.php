<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalibrationUncertaintyBudget extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'uc' => 'decimal:10',
        'veff' => 'decimal:10',
        'coverage_factor' => 'decimal:10',
        'expanded_uncertainty' => 'decimal:10',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(CalibrationUncertaintyComponent::class, 'budget_id')
            ->orderBy('sort_order');
    }
}
