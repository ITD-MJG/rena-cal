<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationPerformanceReading extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'decimal:5',
    ];

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(CalibrationPerformanceMeasurement::class, 'measurement_id');
    }
}
