<?php

namespace App\Models;

use App\Calibration\TemplateRegistry;
use App\Calibration\WorksheetCalculator;
use App\Calibration\WorksheetTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalibrationWorksheet extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'received_date' => 'date',
        'calibration_date' => 'date',
        'device_resolution' => 'decimal:5',
        'range_min' => 'decimal:5',
        'range_max' => 'decimal:5',
        'temperature_start' => 'decimal:1',
        'temperature_end' => 'decimal:1',
        'humidity_start' => 'decimal:1',
        'humidity_end' => 'decimal:1',
        'main_voltage' => 'decimal:1',
        'overrides' => 'array',
        'calculation_snapshot' => 'array',
        'total_score' => 'decimal:2',
        'conclusion_threshold' => 'decimal:2',
        'locked_at' => 'datetime',
        'exported_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(CalibrationInstrument::class, 'worksheet_id')->orderBy('sort_order');
    }

    public function physicalInspections(): HasMany
    {
        return $this->hasMany(CalibrationPhysicalInspection::class, 'worksheet_id')->orderBy('sort_order');
    }

    public function electricalSafetyTests(): HasMany
    {
        return $this->hasMany(CalibrationElectricalSafetyTest::class, 'worksheet_id')->orderBy('sort_order');
    }

    public function performanceMeasurements(): HasMany
    {
        return $this->hasMany(CalibrationPerformanceMeasurement::class, 'worksheet_id')->orderBy('sort_order');
    }

    public function uncertaintyBudgets(): HasMany
    {
        return $this->hasMany(CalibrationUncertaintyBudget::class, 'worksheet_id')->orderBy('sort_order');
    }

    /**
     * The Tier 1 template that produced this worksheet.
     */
    public function template(): WorksheetTemplate
    {
        return app(TemplateRegistry::class)->resolve($this->template_key);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->locked_at !== null;
    }

    /**
     * Resolved Tier 3 value: worksheet override wins over the template default.
     */
    public function resolvedConstant(string $key): mixed
    {
        $override = $this->overrides[$key] ?? null;

        if ($override !== null && array_key_exists('value', $override)) {
            return $override['value'];
        }

        return $this->template()->constant($key);
    }

    /**
     * Record a Tier 3 override. A note is required so an auditor can see why
     * the default was not used.
     */
    public function overrideConstant(string $key, mixed $value, string $note): void
    {
        if (trim($note) === '') {
            throw new \InvalidArgumentException("Override for [{$key}] requires a note.");
        }

        $overrides = $this->overrides ?? [];
        $overrides[$key] = ['value' => $value, 'note' => $note];
        $this->overrides = $overrides;
    }

    /**
     * Score the worksheet, persist the result and return the full breakdown.
     *
     * @return array<string, mixed>
     */
    public function recalculate(): array
    {
        return app(WorksheetCalculator::class)->calculate($this);
    }

    public function computeTotalScore(): float
    {
        return (float) ($this->total_score ?? 0.0);
    }

    public function computeConclusion(): string
    {
        return $this->conclusion ?? 'Tidak Laik Pakai';
    }
}
