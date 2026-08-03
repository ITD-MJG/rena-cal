<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalibrationWorksheet extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'received_date' => 'date',
        'calibration_date' => 'date',
        'temperature_start' => 'decimal:1',
        'temperature_end' => 'decimal:1',
        'humidity_start' => 'decimal:1',
        'humidity_end' => 'decimal:1',
        'main_voltage' => 'decimal:1',
        'total_score' => 'decimal:2',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(CalibrationInstrument::class, 'worksheet_id');
    }

    public function physicalInspections(): HasMany
    {
        return $this->hasMany(CalibrationPhysicalInspection::class, 'worksheet_id');
    }

    public function electricalSafetyTests(): HasMany
    {
        return $this->hasMany(CalibrationElectricalSafetyTest::class, 'worksheet_id');
    }

    public function performanceMeasurements(): HasMany
    {
        return $this->hasMany(CalibrationPerformanceMeasurement::class, 'worksheet_id');
    }

    public function standardCertificates(): HasMany
    {
        return $this->hasMany(StandardInstrumentCertificate::class, 'worksheet_id');
    }

    /**
     * Physical inspection score (6 params, 10 pts each = 60 max)
     */
    public function getPhysicalInspectionScoreAttribute(): int
    {
        return $this->physicalInspections()->where('result', true)->count() * 10;
    }

    /**
     * Electrical safety score (pass all = 60, weighted)
     */
    public function getElectricalSafetyScoreAttribute(): int
    {
        $tests = $this->electricalSafetyTests;
        if ($tests->isEmpty()) {
            return 0;
        }

        $passed = $tests->where('result', 'Memenuhi')->count();

        return (int) round(($passed / $tests->count()) * 60);
    }

    /**
     * Performance score (weighted average of measurement results)
     */
    public function getPerformanceScoreAttribute(): int
    {
        $measurements = $this->performanceMeasurements;
        if ($measurements->isEmpty()) {
            return 0;
        }

        $totalWeight = $measurements->sum('weight');
        if ($totalWeight === 0) {
            return 0;
        }

        $weightedScore = $measurements->reduce(function ($carry, $m) {
            return $carry + ($m->result === 'Lulus' ? $m->weight : 0);
        }, 0);

        return (int) round(($weightedScore / $totalWeight) * 100);
    }

    /**
     * Total score across all sections
     */
    public function computeTotalScore(): float
    {
        return $this->physical_inspection_score
            + $this->electrical_safety_score
            + $this->performance_score;
    }

    /**
     * Determine conclusion based on total score
     */
    public function computeConclusion(): string
    {
        return $this->computeTotalScore() >= 80 ? 'Laik Pakai' : 'Tidak Laik Pakai';
    }
}
