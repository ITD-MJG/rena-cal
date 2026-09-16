<?php

namespace App\Calibration;

/**
 * Tier 1 acceptance criteria for a device type.
 *
 * Everything here is regulation-derived: tolerances, thresholds, weights and
 * the conclusion cut-off. It is intentionally not runtime-editable — a
 * technician who can change a tolerance can make any device pass.
 */
interface WorksheetTemplate
{
    /**
     * Registry key, e.g. 'autoclave'.
     */
    public function key(): string;

    /**
     * Regulation revision this template encodes, e.g. '2024.1'.
     * Stamped onto each worksheet so old certificates stay reproducible.
     */
    public function version(): string;

    /**
     * Human label for the device type.
     */
    public function label(): string;

    /**
     * Fixed physical inspection parameters (LK section 4).
     *
     * @return array<int, array{index:int, name:string, description:string}>
     */
    public function physicalInspectionParameters(): array;

    /**
     * Fixed electrical safety parameters with Tier 1 thresholds (LK section 5).
     *
     * @return array<int, array{
     *     index:int, name:string, method:?string, unit:string,
     *     threshold_operator:string, threshold_value:float, threshold_unit:string
     * }>
     */
    public function electricalSafetyParameters(): array;

    /**
     * Performance parameter definitions (LK section 6).
     *
     * `weight` is the parameter's share of the performance section, in
     * points. `scored` marks whether the parameter is summed into the
     * conclusion — Autoclave.xlsx computes time accuracy but never adds it to
     * the total (PENGOLAHAN DATA!K70 sums only H70:H72, where H72 is the
     * temperature row).
     *
     * @return array<int, array{
     *     index:int, name:string, unit:string,
     *     setting_from:?string, tolerance:float, allowed_deviation:string,
     *     weight:float, scored:bool, correction_role:?string
     * }>
     */
    public function performanceParameters(): array;

    /**
     * Uncertainty budget structure per group, ported from KETIDAKPASTIAN.
     *
     * @return array<int, array{
     *     key:string, label:string, unit:string, instrument_role:?string,
     *     components: array<int, array{
     *         name:string, symbol:string, unit:string, distribution:string,
     *         divisor_expr:string, v:?float, c:float,
     *         source_type:string, source_ref:string
     *     }>
     * }>
     */
    public function uncertaintyBudgets(): array;

    /**
     * Tier 3 methodology constants. Technicians may override these per
     * worksheet, with a note.
     *
     * @return array<string, float>
     */
    public function constants(): array;

    /**
     * A single Tier 3 default, or null when unknown.
     */
    public function constant(string $key): ?float;

    /**
     * Score cut-off for "Laik Pakai".
     */
    public function conclusionThreshold(): float;

    /**
     * Maximum points for the physical inspection section.
     * Autoclave.xlsx PENGOLAHAN DATA!P29 scores the ratio of "Baik" rows out
     * of this maximum.
     */
    public function physicalInspectionWeight(): float;

    /**
     * Maximum points for the electrical safety section.
     */
    public function electricalSafetyWeight(): float;

    /**
     * Electrical safety is all-or-nothing when true: the section scores full
     * weight only when every parameter passes, otherwise zero. Matches
     * Autoclave.xlsx PENGOLAHAN DATA!W38.
     */
    public function electricalSafetyRequiresAll(): bool;

    /**
     * Minimum score a single performance parameter must reach to contribute
     * anything. A parameter scoring below this contributes zero. Matches
     * Autoclave.xlsx PENGOLAHAN DATA!J65 / J66 (`IF(X<70,0,X)`).
     */
    public function performanceMinimumScore(): float;

    /**
     * Uncertainty budget group key for a performance parameter at a given
     * instrument setting. Temperature maps to 121/134 groups; time has one.
     *
     * @param  array<string, mixed>  $parameter  One entry from performanceParameters().
     */
    public function uncertaintyBudgetKeyFor(array $parameter, ?float $setting): ?string;
}
