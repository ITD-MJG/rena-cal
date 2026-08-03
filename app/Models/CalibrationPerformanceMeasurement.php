<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationPerformanceMeasurement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'setting_value' => 'decimal:2',
        'measurement_1' => 'decimal:5',
        'measurement_2' => 'decimal:5',
        'measurement_3' => 'decimal:5',
        'mean' => 'decimal:5',
        'std_dev' => 'decimal:8',
        'corrected_mean' => 'decimal:5',
        'correction' => 'decimal:5',
        'uncertainty_u95' => 'decimal:10',
        'total_correction_u95' => 'decimal:10',
        'tolerance' => 'decimal:2',
    ];

    public function worksheet()
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }

    /**
     * Compute mean, std dev, corrected mean, and pass/fail
     */
    public function computeStatistics(): void
    {
        $readings = array_filter([
            $this->measurement_1,
            $this->measurement_2,
            $this->measurement_3,
        ], fn ($v) => $v !== null);

        if (count($readings) < 2) {
            return;
        }

        $n = count($readings);
        $this->mean = array_sum($readings) / $n;

        // Sample standard deviation
        $sumSquaredDiff = array_reduce($readings, function ($carry, $reading) {
            return $carry + pow($reading - $this->mean, 2);
        }, 0);
        $this->std_dev = sqrt($sumSquaredDiff / ($n - 1));

        // Type A uncertainty (repeatability)
        $uA = $this->std_dev / sqrt($n);

        // Type B uncertainty (simplified: certificate 0.036°C, drift 0.01°C, resolution 0.05°C, human 0.00693s)
        $uB_cert = 0.036 / 2;
        $uB_drift = 0.01 / sqrt(3);
        $uB_resolution = 0.05 / sqrt(3);
        $uB_human = 0.00693 / sqrt(3);

        // Combined standard uncertainty
        $uc = sqrt(
            pow($uA, 2)
            + pow($uB_cert, 2)
            + pow($uB_drift, 2)
            + pow($uB_resolution, 2)
            + pow($uB_human, 2)
        );

        // Coverage factor (approximate for 95% confidence, df ~ 8)
        $k = 2.306;

        $this->uncertainty_u95 = $uc;
        $this->total_correction_u95 = $k * $uc;

        // Correction from standard certificate (simplified: use mean - setting as correction)
        $this->correction = $this->mean - $this->setting_value;
        $this->corrected_mean = $this->mean;

        // Pass/fail
        $deviation = abs($this->correction);
        $this->result = $deviation <= $this->tolerance ? 'Lulus' : 'Tidak Lulus';

        $this->save();
    }

    /**
     * Lookup tolerance from regulation
     */
    public static function lookupTolerance(string $parameterName, string $settingUnit): ?array
    {
        $tolerances = [
            'Akurasi Temperatur' => [
                '121' => ['allowed' => '118°C s.d 123°C', 'tolerance' => 2],
                '134' => ['allowed' => '131°C s.d 137°C', 'tolerance' => 2],
            ],
            'Akurasi Waktu' => [
                '121' => ['allowed' => '≥ 15 menit', 'tolerance' => 15],
                '134' => ['allowed' => '≥ 3 menit', 'tolerance' => 3],
            ],
        ];

        return $tolerances[$parameterName][strval($settingUnit)] ?? null;
    }
}
