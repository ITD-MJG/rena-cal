<?php

namespace App\Calibration;

use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\StudentT;

/**
 * Ports the KETIDAKPASTIAN sheet: one uncertainty budget per group.
 *
 * For each component the engine resolves a magnitude `U`, a divisor and a
 * degrees-of-freedom `v`, then:
 *
 *   u        = U / divisor
 *   (u.c)    = u * c
 *   uc       = sqrt( Σ (u.c)² )
 *   veff     = uc⁴ / Σ ( (u.c)⁴ / v )
 *   k        = TINV(0.05, veff)          (two-tailed, 95% confidence)
 *   U        = k * uc
 *
 * The magnitudes come from four source types, matching the workbook's cell
 * references:
 *
 *   derived     performance standard deviation          (X47 / X57)
 *   certificate standard instrument certificate `u`     (SERT. DATA LOGGER!H15)
 *   constant    a resolved Tier 3 methodology constant  (E12, W11, W13, W14)
 *   device_spec worksheet device resolution, halved      (E13 = 0.5 * D5)
 */
class UncertaintyEngine
{
    /**
     * Resolve a symbolic divisor from the template.
     *
     * @param  string  $expr  '2', 'sqrt(3)', 'sqrt(n)', '1'
     */
    public function resolveDivisor(string $expr, int $readingCount): float
    {
        $expr = trim($expr);

        return match (true) {
            $expr === 'sqrt(n)' => sqrt(max($readingCount, 1)),
            str_starts_with($expr, 'sqrt(') => sqrt((float) rtrim(substr($expr, 5), ')')),
            default => (float) $expr,
        };
    }

    /**
     * Compute one budget from already-resolved component magnitudes.
     *
     * @param  array<int, array{
     *     name:string, symbol:?string, unit:string, distribution:string,
     *     u_value:float, divisor:float, v:?float, c:float
     * }>  $components
     * @return array{
     *     uc:float, veff:float, coverage_factor:float, expanded_uncertainty:float,
     *     components: array<int, array<string, mixed>>
     * }
     */
    public function compute(array $components): array
    {
        $sumSquares = 0.0;
        $sumFourthOverV = 0.0;
        $resolved = [];

        foreach ($components as $component) {
            $divisor = $component['divisor'] > 0.0 ? $component['divisor'] : 1.0;
            $u = $component['u_value'] / $divisor;
            $contribution = $u * $component['c'];
            $square = $contribution ** 2;
            $fourthOverV = $component['v'] !== null && $component['v'] > 0.0
                ? ($square ** 2) / $component['v']
                : 0.0;

            $sumSquares += $square;
            $sumFourthOverV += $fourthOverV;

            $resolved[] = [
                'component_name' => $component['name'],
                'symbol' => $component['symbol'] ?? null,
                'unit' => $component['unit'],
                'distribution' => $component['distribution'],
                'u_value' => $component['u_value'],
                'divisor' => $divisor,
                'degrees_of_freedom' => $component['v'],
                'sensitivity_coefficient' => $component['c'],
                'u_contribution' => $contribution,
                'u_contribution_sq' => $square,
                'u_contribution_4th_over_v' => $fourthOverV,
            ];
        }

        $uc = sqrt($sumSquares);

        // veff is only meaningful when at least one component carries a v.
        $veff = ($sumFourthOverV > 0.0 && $uc > 0.0)
            ? ($uc ** 4) / $sumFourthOverV
            : 0.0;

        $k = $veff > 0.0 ? (float) StudentT::inverse(0.05, (int) round($veff)) : 2.0;

        return [
            'uc' => $uc,
            'veff' => $veff,
            'coverage_factor' => $k,
            'expanded_uncertainty' => $k * $uc,
            'components' => $resolved,
        ];
    }
}
