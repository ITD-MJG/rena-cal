<?php

namespace App\Calibration;

use App\Models\CalibrationWorksheet;

/**
 * Seeds a worksheet's child rows from its Tier 1 template.
 *
 * Called when a worksheet is created so the technician sees the fixed
 * parameters for the device type and only fills in measurements. Re-running is
 * idempotent: rows are matched on their parameter index and updated in place.
 */
class WorksheetHydrator
{
    public function hydrate(CalibrationWorksheet $worksheet): void
    {
        $template = $worksheet->template();

        $this->hydratePhysicalInspections($worksheet, $template);
        $this->hydrateElectricalSafetyTests($worksheet, $template);
        $this->hydratePerformanceMeasurements($worksheet, $template);
    }

    protected function hydratePhysicalInspections(CalibrationWorksheet $worksheet, WorksheetTemplate $template): void
    {
        foreach ($template->physicalInspectionParameters() as $order => $parameter) {
            $worksheet->physicalInspections()->updateOrCreate(
                ['parameter_index' => $parameter['index']],
                [
                    'sort_order' => $order,
                    'parameter_name' => $parameter['name'],
                    'description' => $parameter['description'],
                ],
            );
        }
    }

    protected function hydrateElectricalSafetyTests(CalibrationWorksheet $worksheet, WorksheetTemplate $template): void
    {
        foreach ($template->electricalSafetyParameters() as $order => $parameter) {
            $worksheet->electricalSafetyTests()->updateOrCreate(
                ['parameter_index' => $parameter['index']],
                [
                    'sort_order' => $order,
                    'parameter_name' => $parameter['name'],
                    'measurement_method' => $parameter['method'],
                    'unit' => $parameter['unit'],
                    'threshold_operator' => $parameter['threshold_operator'],
                    'threshold_value' => $parameter['threshold_value'],
                    'threshold_unit' => $parameter['threshold_unit'],
                ],
            );
        }
    }

    protected function hydratePerformanceMeasurements(CalibrationWorksheet $worksheet, WorksheetTemplate $template): void
    {
        foreach ($template->performanceParameters() as $order => $parameter) {
            $measurement = $worksheet->performanceMeasurements()->updateOrCreate(
                ['parameter_index' => $parameter['index']],
                [
                    'sort_order' => $order,
                    'parameter_name' => $parameter['name'],
                    'setting_unit' => $parameter['unit'],
                    'setting_value' => $this->defaultSetting($worksheet, $parameter),
                    'allowed_deviation' => $parameter['allowed_deviation'],
                    'tolerance' => $parameter['tolerance'],
                    'weight' => (int) round($parameter['weight']),
                ],
            );

            // The workbook repeats the same reading three times (I47:T47).
            if ($measurement->readings()->doesntExist()) {
                foreach (range(1, 3) as $repeat) {
                    $measurement->readings()->create([
                        'sort_order' => $repeat,
                        'point_label' => "Pembacaan {$repeat}",
                        'value' => 0,
                        'unit' => $parameter['unit'],
                    ]);
                }
            }
        }
    }

    /**
     * The workbook derives the setting from the chosen sterilization
     * temperature (LEMBAR KERJA!F57 and C57).
     *
     * @param  array<string, mixed>  $parameter
     */
    protected function defaultSetting(CalibrationWorksheet $worksheet, array $parameter): ?float
    {
        return match ($parameter['setting_from'] ?? null) {
            'temperature' => 134.0,
            'sterilization_time' => (float) ($worksheet->resolvedConstant('sterilization_time_134') ?? 3.0),
            default => null,
        };
    }
}
