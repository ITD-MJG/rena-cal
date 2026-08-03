<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationElectricalSafetyTest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'raw_value' => 'decimal:5',
        'certificate_correction' => 'decimal:5',
        'corrected_value' => 'decimal:5',
        'threshold_value' => 'decimal:5',
    ];

    public function worksheet()
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }

    /**
     * Compute corrected value and pass/fail result
     */
    public function computeResult(): void
    {
        $this->corrected_value = $this->raw_value + ($this->certificate_correction ?? 0);

        $passes = match ($this->threshold_operator) {
            '≤' => $this->corrected_value <= $this->threshold_value,
            '>' => $this->corrected_value > $this->threshold_value,
            default => false,
        };

        $this->result = $passes ? 'Memenuhi' : 'Tidak Memenuhi';
        $this->save();
    }

    /**
     * Lookup threshold from regulation reference table
     */
    public static function lookupThreshold(string $parameterName, ?string $class, ?string $typeVariant, ?string $method): ?array
    {
        $thresholds = [
            'Resistansi pembumian Kabel dapat dilepas (DPS)' => ['operator' => '≤', 'value' => 0.3, 'unit' => 'Ω'],
            'Resistansi pembumian Kabel tidak dapat dilepas (NPS)' => ['operator' => '≤', 'value' => 0.3, 'unit' => 'Ω'],
            'Resistansi pembumian Instalasi permanen (PIE)' => ['operator' => '≤', 'value' => 0.3, 'unit' => 'Ω'],
            'Resistansi pembumian Protektif' => ['operator' => '≤', 'value' => 0.3, 'unit' => 'Ω'],
            'Resistansi pembumian Sistem elektromedik dengan kotak kontak multipel' => ['operator' => '≤', 'value' => 0.5, 'unit' => 'Ω'],
            'Resistansi Isolasi' => ['operator' => '>', 'value' => 2, 'unit' => 'MΩ'],
        ];

        // Leakage current lookup by class + type + method
        $leakageThresholds = [
            'I' => [
                'B' => ['Langsung' => 500, 'Diferensial' => 500, 'Alternative' => 1000],
                'BF' => ['Langsung' => 500, 'Diferensial' => 500, 'Alternative' => 1000],
                'CF' => ['Langsung' => 500, 'Diferensial' => 500, 'Alternative' => 1000],
            ],
            'II' => [
                'B' => ['Langsung' => 100, 'Diferensial' => 100, 'Alternative' => 500],
                'BF' => ['Langsung' => null, 'Diferensial' => 100, 'Alternative' => 500],
                'CF' => ['Langsung' => 100, 'Diferensial' => 100, 'Alternative' => 500],
            ],
        ];

        if (str_contains($parameterName, 'Arus bocor')) {
            $value = $leakageThresholds[$class][$typeVariant][$method] ?? null;

            return $value !== null
                ? ['operator' => '≤', 'value' => $value, 'unit' => 'µA']
                : null;
        }

        return $thresholds[$parameterName] ?? null;
    }
}
