<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationUncertaintyComponent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'u_value' => 'decimal:10',
        'divisor' => 'decimal:10',
        'degrees_of_freedom' => 'decimal:5',
        'sensitivity_coefficient' => 'decimal:10',
        'u_contribution' => 'decimal:10',
        'u_contribution_sq' => 'decimal:10',
        'u_contribution_4th_over_v' => 'decimal:10',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(CalibrationUncertaintyBudget::class, 'budget_id');
    }
}
