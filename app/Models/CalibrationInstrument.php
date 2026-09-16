<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationInstrument extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'calibration_date' => 'date',
        'correction_data' => 'array',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }

    /**
     * Apply the certificate regression to a raw reading.
     */
    public function applyCorrection(?float $reading): ?float
    {
        if ($reading === null) {
            return null;
        }

        $slope = $this->correction_data['slope'] ?? 1.0;
        $intercept = $this->correction_data['intercept'] ?? 0.0;

        return ($reading * $slope) + $intercept;
    }
}
