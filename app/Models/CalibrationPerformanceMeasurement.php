<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalibrationPerformanceMeasurement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'setting_value' => 'decimal:5',
        'mean' => 'decimal:10',
        'std_dev' => 'decimal:10',
        'corrected_mean' => 'decimal:10',
        'correction' => 'decimal:10',
        'uncertainty_u95' => 'decimal:10',
        'total_correction_u95' => 'decimal:10',
        'tolerance' => 'decimal:5',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(CalibrationPerformanceReading::class, 'measurement_id')
            ->orderBy('sort_order');
    }

    /**
     * Values in reading order, for the calculation engine.
     *
     * @return array<int, float>
     */
    public function values(): array
    {
        return $this->readings
            ->pluck('value')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
