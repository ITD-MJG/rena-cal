<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationElectricalSafetyTest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'raw_value' => 'decimal:5',
        'certificate_correction' => 'decimal:5',
        'corrected_value' => 'decimal:5',
        'threshold_value' => 'decimal:5',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }

    /**
     * Compare the corrected value against the template threshold.
     */
    public function evaluate(): ?string
    {
        if ($this->corrected_value === null || $this->threshold_value === null) {
            return null;
        }

        $corrected = (float) $this->corrected_value;
        $threshold = (float) $this->threshold_value;

        $passes = match ($this->threshold_operator) {
            '≤', '<=' => $corrected <= $threshold,
            '≥', '>=' => $corrected >= $threshold,
            '<' => $corrected < $threshold,
            '>' => $corrected > $threshold,
            default => null,
        };

        return $passes === null ? null : ($passes ? 'Memenuhi' : 'Tidak Memenuhi');
    }
}
