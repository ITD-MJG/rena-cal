<?php

namespace App\Calibration;

use App\Models\CalibrationInstrument;
use App\Models\CalibrationPerformanceMeasurement;
use App\Models\CalibrationWorksheet;
use Illuminate\Support\Collection;

/**
 * Scores a worksheet and resolves its uncertainty budgets.
 *
 * Every formula here is traced to a cell in Autoclave.xlsx. The scoring is
 * deliberately odd in places — see the inline cell references — because the
 * goal is to reproduce the issued certificate, not to redesign the method.
 *
 * Section maxima: physical 10, electrical 40, performance 50 → total 100.
 * Conclusion threshold: 90 (PENGOLAHAN DATA!O70, LAPORAN HASIL!B52).
 */
class WorksheetCalculator
{
    public function __construct(
        private readonly UncertaintyEngine $uncertainty = new UncertaintyEngine,
    ) {}

    /**
     * Score every section and write the result back onto the model.
     *
     * @return array{
     *     physical_score: float, electrical_score: float, performance_score: float,
     *     total_score: float, threshold: float, conclusion: string,
     *     performance: array<int, array<string, mixed>>,
     *     electrical: array<int, array<string, mixed>>,
     *     uncertainty: array<string, array<string, mixed>>
     * }
     */
    public function calculate(CalibrationWorksheet $worksheet): array
    {
        $worksheet->loadMissing([
            'physicalInspections',
            'electricalSafetyTests',
            'performanceMeasurements.readings',
            'instruments',
            'uncertaintyBudgets.components',
        ]);

        $template = $worksheet->template();

        // Order matters: the uncertainty budgets consume each parameter's
        // standard deviation, which the performance pass derives from the
        // readings. Compute statistics first, then resolve budgets.
        $statistics = $this->computePerformanceStatistics($worksheet, $template);

        $uncertainty = $this->resolveUncertainty($worksheet, $template);

        $physical = $this->scorePhysical($worksheet, $template);
        $electrical = $this->scoreElectrical($worksheet, $template);
        $performance = $this->scorePerformance($worksheet, $template, $uncertainty, $statistics);

        $total = $physical['score'] + $electrical['score'] + $performance['score'];
        $threshold = $template->conclusionThreshold();

        $result = [
            'physical_score' => round($physical['score'], 2),
            'electrical_score' => round($electrical['score'], 2),
            'performance_score' => round($performance['score'], 2),
            'total_score' => round($total, 2),
            'threshold' => $threshold,
            'conclusion' => $total >= $threshold ? 'Laik Pakai' : 'Tidak Laik Pakai',
            'physical' => $physical,
            'electrical' => $electrical,
            'performance' => $performance,
            'uncertainty' => $uncertainty,
        ];

        $worksheet->forceFill([
            'total_score' => $result['total_score'],
            'conclusion_threshold' => $threshold,
            'conclusion' => $result['conclusion'],
            'calculation_snapshot' => [
                'engine_version' => $worksheet->engine_version,
                'template_key' => $template->key(),
                'template_version' => $template->version(),
                'physical_score' => $result['physical_score'],
                'electrical_score' => $result['electrical_score'],
                'performance_score' => $result['performance_score'],
                'total_score' => $result['total_score'],
                'threshold' => $threshold,
                'conclusion' => $result['conclusion'],
                'uncertainty' => array_map(
                    fn (array $budget) => [
                        'uc' => $budget['uc'],
                        'veff' => $budget['veff'],
                        'k' => $budget['coverage_factor'],
                        'u' => $budget['expanded_uncertainty'],
                    ],
                    $uncertainty,
                ),
            ],
        ])->save();

        return $result;
    }

    /**
     * PENGOLAHAN DATA!P29 = COUNTIF(K29:N34,"baik") / COUNTA(K29:N34) * 10.
     *
     * @return array{score:float, max:float, passed:int, total:int}
     */
    protected function scorePhysical(CalibrationWorksheet $worksheet, WorksheetTemplate $template): array
    {
        $inspections = $worksheet->physicalInspections;
        $total = $inspections->count();
        $passed = $inspections->where('result', true)->count();
        $max = $template->physicalInspectionWeight();

        return [
            'score' => $total > 0 ? ($passed / $total) * $max : 0.0,
            'max' => $max,
            'passed' => $passed,
            'total' => $total,
        ];
    }

    /**
     * PENGOLAHAN DATA!W38 = IF(COUNTIF(T38:V41,"memenuhi") < 4, 0, 40).
     *
     * @return array{score:float, max:float, passed:int, total:int, requires_all:bool, parameters:array<int, array<string, mixed>>}
     */
    protected function scoreElectrical(CalibrationWorksheet $worksheet, WorksheetTemplate $template): array
    {
        $tests = $worksheet->electricalSafetyTests;
        $analyzer = $worksheet->instruments->firstWhere('role', 'esa');
        $total = $tests->count();
        $passed = 0;
        $resolved = [];

        foreach ($tests as $test) {
            // LEMBAR KERJA J38 = P38 (raw) → N38 = J38 * slope + intercept.
            $corrected = $test->corrected_value;

            if ($corrected === null && $test->raw_value !== null) {
                // N38 = J38 * slope + intercept. Without a bound analyzer the
                // raw reading is the best available estimate (slope 1,
                // intercept 0), never a null that silently fails the section.
                $corrected = $analyzer !== null
                    ? $analyzer->applyCorrection((float) $test->raw_value)
                    : (float) $test->raw_value;
            }

            $result = $this->evaluateThreshold($corrected, $test->threshold_operator, $test->threshold_value);

            $test->forceFill([
                'corrected_value' => $corrected,
                'result' => $result,
            ])->save();

            if ($result === 'Memenuhi') {
                $passed++;
            }

            $resolved[] = [
                'index' => $test->parameter_index,
                'name' => $test->parameter_name,
                'raw_value' => $test->raw_value,
                'corrected_value' => $corrected,
                'threshold_operator' => $test->threshold_operator,
                'threshold_value' => $test->threshold_value,
                'result' => $result,
            ];
        }

        $max = $template->electricalSafetyWeight();
        $requiresAll = $template->electricalSafetyRequiresAll();

        // W38 = IF(COUNTIF(T38:V41,"memenuhi") < 4, 0, 40). A parameter that
        // was never measured counts as not meeting the requirement.
        $score = match (true) {
            $total === 0 => 0.0,
            $requiresAll => $passed === $total ? $max : 0.0,
            default => ($passed / $total) * $max,
        };

        return [
            'score' => $score,
            'max' => $max,
            'passed' => $passed,
            'total' => $total,
            'requires_all' => $requiresAll,
            'parameters' => $resolved,
        ];
    }

    /**
     * LEMBAR KERJA T38 = IF(J38 <= R38, "Memenuhi", "Tidak Memenuhi").
     *
     * A null measurement never satisfies a numeric threshold — this is what
     * makes an unmeasured parameter fail the electrical section in the
     * workbook (the "-" row).
     */
    protected function evaluateThreshold(?float $value, ?string $operator, ?float $threshold): string
    {
        if ($value === null || $threshold === null) {
            return 'Tidak Memenuhi';
        }

        $passes = match ($operator) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            default => $value <= $threshold,
        };

        return $passes ? 'Memenuhi' : 'Tidak Memenuhi';
    }

    /**
     * Derive each parameter's mean and standard deviation from its readings,
     * and apply the bound instrument's certificate regression.
     *
     * U47 = AVERAGE(I47:T47); X47 = STDEV(I47:T47) (Excel STDEV is the sample
     * standard deviation); C51 = U47 * slope + intercept; F51 = C51 - C47.
     *
     * Runs before the uncertainty budgets because the repeated-reading
     * component consumes `std_dev`.
     *
     * @return array<int, array<string, mixed>> keyed by parameter_index
     */
    protected function computePerformanceStatistics(
        CalibrationWorksheet $worksheet,
        WorksheetTemplate $template,
    ): array {
        $measurements = $worksheet->performanceMeasurements->keyBy('parameter_index');
        $statistics = [];

        foreach ($template->performanceParameters() as $definition) {
            /** @var CalibrationPerformanceMeasurement|null $measurement */
            $measurement = $measurements->get($definition['index']);

            if ($measurement === null) {
                continue;
            }

            $values = $measurement->readings->pluck('value')->map(fn ($v) => (float) $v)->all();
            $count = count($values);
            $mean = $count > 0 ? array_sum($values) / $count : null;
            $stdDev = $count > 1 ? $this->sampleStdDev($values, $mean) : 0.0;

            $instrument = $worksheet->instruments->firstWhere('role', $definition['correction_role'] ?? '');
            $correctedMean = ($mean !== null && $instrument !== null)
                ? $instrument->applyCorrection($mean)
                : $mean;

            $setting = $measurement->setting_value === null ? null : (float) $measurement->setting_value;
            $correction = ($correctedMean !== null && $setting !== null)
                ? $correctedMean - $setting
                : null;

            $measurement->forceFill([
                'mean' => $mean,
                'std_dev' => $stdDev,
                'corrected_mean' => $correctedMean,
                'correction' => $correction,
            ])->save();

            $statistics[$definition['index']] = [
                'mean' => $mean,
                'std_dev' => $stdDev,
                'corrected_mean' => $correctedMean,
                'correction' => $correction,
                'setting' => $setting,
            ];
        }

        return $statistics;
    }

    /**
     * PENGOLAHAN DATA!X51 / X61 = IF(<passed>, 100, 0); J65/J66 discard
     * anything below the minimum; R65/R66 apply the parameter weight; K70
     * sums only the parameters marked `scored`.
     *
     * @param  array<string, array<string, mixed>>  $uncertainty
     * @param  array<int, array<string, mixed>>  $statistics
     * @return array{score:float, max:float, parameters:array<int, array<string, mixed>>}
     */
    protected function scorePerformance(
        CalibrationWorksheet $worksheet,
        WorksheetTemplate $template,
        array $uncertainty,
        array $statistics,
    ): array {
        $measurements = $worksheet->performanceMeasurements->keyBy('parameter_index');
        $minimum = $template->performanceMinimumScore();
        $definitions = $template->performanceParameters();
        $score = 0.0;
        $resolved = [];

        foreach ($definitions as $definition) {
            /** @var CalibrationPerformanceMeasurement|null $measurement */
            $measurement = $measurements->get($definition['index']);
            $stats = $statistics[$definition['index']] ?? null;

            if ($measurement === null || $stats === null) {
                $resolved[] = array_merge($definition, ['result' => null, 'raw_score' => 0.0, 'awarded' => 0.0]);

                continue;
            }

            $setting = $stats['setting'];
            $correction = $stats['correction'];

            $budgetKey = $template->uncertaintyBudgetKeyFor($definition, $setting);
            $budget = $budgetKey !== null ? ($uncertainty[$budgetKey] ?? null) : null;
            $u95 = $budget['expanded_uncertainty'] ?? null;

            // L51 = ABS(F51) + ABS(I51); U51 = IF(L51 <= S51, "Lulus", "Tidak").
            $totalCorrection = ($correction === null || $u95 === null)
                ? null
                : abs($correction) + abs($u95);

            $tolerance = (float) $definition['tolerance'];
            $passes = $totalCorrection !== null && $totalCorrection <= $tolerance;
            $result = $passes ? 'Lulus' : 'Tidak Lulus';
            $rawScore = $passes ? 100.0 : 0.0;

            // J65/J66: below the minimum contributes nothing.
            $effective = $rawScore < $minimum ? 0.0 : $rawScore;

            // R65/R66: the parameter's weighted share.
            $weighted = $effective * ($definition['weight'] / 100.0);

            // H72 = IF(R65<50,0,R65): only the full weighted share is counted.
            $awarded = $definition['scored'] && $weighted >= $definition['weight'] ? $weighted : 0.0;

            $measurement->forceFill([
                'uncertainty_u95' => $u95,
                'total_correction_u95' => $totalCorrection,
                'tolerance' => $tolerance,
                'allowed_deviation' => $definition['allowed_deviation'],
                'weight' => (int) round($definition['weight']),
                'result' => $result,
            ])->save();

            $score += $awarded;

            $resolved[] = array_merge($definition, [
                'result' => $result,
                'mean' => $stats['mean'],
                'corrected_mean' => $stats['corrected_mean'],
                'correction' => $correction,
                'raw_score' => $rawScore,
                'effective_score' => $effective,
                'weighted_score' => $weighted,
                'awarded' => $awarded,
                'uncertainty_u95' => $u95,
                'total_correction_u95' => $totalCorrection,
                'budget_key' => $budgetKey,
            ]);
        }

        return [
            'score' => $score,
            'max' => array_sum(array_column($definitions, 'weight')),
            'parameters' => $resolved,
        ];
    }

    /**
     * Excel STDEV over n readings (sample, n-1 denominator).
     *
     * @param  array<int, float>  $values
     */
    protected function sampleStdDev(array $values, ?float $mean = null): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean ??= array_sum($values) / $count;
        $sumSquares = 0.0;

        foreach ($values as $value) {
            $sumSquares += ($value - $mean) ** 2;
        }

        return sqrt($sumSquares / ($count - 1));
    }

    /**
     * Resolve every budget the template defines and persist it.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function resolveUncertainty(CalibrationWorksheet $worksheet, WorksheetTemplate $template): array
    {
        $instruments = $worksheet->instruments->keyBy('role');
        $measurements = $worksheet->performanceMeasurements->keyBy('parameter_index');
        $result = [];

        foreach ($template->uncertaintyBudgets() as $budget) {
            $components = [];
            $readingCount = 0;

            foreach ($budget['components'] as $component) {
                // The repeated-reading component is the only one whose divisor
                // depends on how many readings the parameter actually has.
                if ($component['divisor_expr'] === 'sqrt(n)') {
                    $readingCount = (int) ($this->measurementForBudget(
                        $template,
                        $budget,
                        $measurements,
                    )?->readings->count() ?? 0);
                }
            }

            foreach ($budget['components'] as $component) {
                $components[] = [
                    'name' => $component['name'],
                    'symbol' => $component['symbol'],
                    'unit' => $component['unit'],
                    'distribution' => $component['distribution'],
                    'u_value' => $this->resolveMagnitude(
                        $component,
                        $template,
                        $worksheet,
                        $instruments,
                        $measurements,
                        $budget,
                    ),
                    'divisor' => $this->uncertainty->resolveDivisor($component['divisor_expr'], $readingCount),
                    // KETIDAKPASTIAN!G10 = D4 - 1: the repeated-reading
                    // component carries n-1 degrees of freedom, not null.
                    'v' => $component['source_type'] === 'derived'
                        ? max($readingCount - 1, 1)
                        : $component['v'],
                    'c' => $component['c'],
                ];
            }

            $computed = $this->uncertainty->compute($components);

            $this->persistBudget($worksheet, $budget, $computed);

            $result[$budget['key']] = array_merge($computed, [
                'key' => $budget['key'],
                'label' => $budget['label'],
                'unit' => $budget['unit'],
            ]);
        }

        return $result;
    }

    /**
     * Find the performance measurement a budget scores. The template's
     * `uncertaintyBudgetKeyFor` maps a parameter to a budget key; invert it by
     * matching the budget key back to its parameter.
     *
     * @param  array<string, mixed>  $budget
     * @param  Collection<int, CalibrationPerformanceMeasurement>  $measurements
     */
    protected function measurementForBudget(
        WorksheetTemplate $template,
        array $budget,
        $measurements,
    ): ?CalibrationPerformanceMeasurement {
        foreach ($template->performanceParameters() as $definition) {
            $measurement = $measurements->get($definition['index']);

            if ($measurement === null) {
                continue;
            }

            $setting = $measurement->setting_value === null ? null : (float) $measurement->setting_value;

            if ($template->uncertaintyBudgetKeyFor($definition, $setting) === $budget['key']) {
                return $measurement;
            }
        }

        return null;
    }

    /**
     * Resolve a component's `U` magnitude from its declared source.
     *
     * @param  array<string, mixed>  $component
     * @param  Collection<string, CalibrationInstrument>  $instruments
     * @param  Collection<int, CalibrationPerformanceMeasurement>  $measurements
     * @param  array<string, mixed>  $budget
     */
    protected function resolveMagnitude(
        array $component,
        WorksheetTemplate $template,
        CalibrationWorksheet $worksheet,
        $instruments,
        $measurements,
        array $budget,
    ): float {
        return match ($component['source_type']) {
            'derived' => $this->derivedStdDev($component['source_ref'], $template, $budget, $measurements),
            'certificate' => $this->certificateU($component['source_ref'], $instruments, $budget),
            'constant' => (float) ($worksheet->resolvedConstant($component['source_ref']) ?? 0.0),
            'device_spec' => $this->deviceSpecMagnitude($component['source_ref'], $worksheet),
            default => 0.0,
        };
    }

    /**
     * X47 / X57: STDEV of the readings for the parameter this budget scores.
     *
     * @param  Collection<int, CalibrationPerformanceMeasurement>  $measurements
     */
    protected function derivedStdDev(string $ref, WorksheetTemplate $template, array $budget, $measurements): float
    {
        if ($ref !== 'performance.std_dev') {
            return 0.0;
        }

        $measurement = $this->measurementForBudget($template, $budget, $measurements);

        if ($measurement === null) {
            return 0.0;
        }

        return (float) ($measurement->std_dev ?? 0.0);
    }

    /**
     * E11 / E23 / E10: the standard instrument's certificate uncertainty,
     * read from the instrument bound to the budget's role.
     *
     * @param  Collection<string, CalibrationInstrument>  $instruments
     * @param  array<string, mixed>  $budget
     */
    protected function certificateU(string $ref, $instruments, array $budget): float
    {
        if (! str_contains($ref, 'instrument.correction_data.u')) {
            return 0.0;
        }

        $instrument = $instruments->get($budget['instrument_role'] ?? '');

        if ($instrument === null) {
            return 0.0;
        }

        return (float) ($instrument->correction_data['u'] ?? 0.0);
    }

    /**
     * E13 = 0.5 * D5: half the device resolution, unless the component names a
     * different spec (the time budget uses V5's resolution constant).
     */
    protected function deviceSpecMagnitude(string $ref, CalibrationWorksheet $worksheet): float
    {
        if ($ref === 'time_resolution') {
            return 0.5 * (float) ($worksheet->resolvedConstant('time_resolution') ?? 0.0);
        }

        return 0.5 * (float) ($worksheet->device_resolution ?? 0.0);
    }

    /**
     * Persist a resolved budget and its components so the certificate can be
     * reproduced without re-running the engine.
     *
     * @param  array<string, mixed>  $budget
     * @param  array<string, mixed>  $computed
     */
    protected function persistBudget(CalibrationWorksheet $worksheet, array $budget, array $computed): void
    {
        $model = $worksheet->uncertaintyBudgets()->updateOrCreate(
            ['key' => $budget['key']],
            [
                'label' => $budget['label'],
                'unit' => $budget['unit'],
                'uc' => $computed['uc'],
                'veff' => $computed['veff'],
                'coverage_factor' => $computed['coverage_factor'],
                'expanded_uncertainty' => $computed['expanded_uncertainty'],
            ],
        );

        $model->components()->delete();

        foreach ($computed['components'] as $index => $component) {
            $model->components()->create(array_merge($component, [
                'sort_order' => $index,
                'source_type' => $budget['components'][$index]['source_type'],
                'source_ref' => $budget['components'][$index]['source_ref'],
            ]));
        }
    }
}
