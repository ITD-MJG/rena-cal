<?php

use App\Calibration\UncertaintyEngine;
use App\Calibration\WorksheetHydrator;
use App\Models\CalibrationInstrument;
use App\Models\CalibrationWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceName;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Ground truth: Autoclave.xlsx, AUTOCLAVE_A250705-510.
 *
 * Expected values come from the workbook's own formulas, not from the
 * implementation:
 *   KETIDAKPASTIAN!K15 uc, K16 veff, K17 TINV(0.05,veff), K18 = k * uc
 *   PENGOLAHAN DATA!U47 AVERAGE, X47 STDEV, C51 corrected mean, L51, U51
 *   PENGOLAHAN DATA!P29, W38, K70, O70
 */
function makeReferenceWorksheet(): CalibrationWorksheet
{
    $service = Service::create(['name' => 'Instalasi sterilisasi pusat', 'slug' => 'sterilisasi']);

    $device = Device::create([
        'deviceId' => Str::uuid(),
        'device_number' => 'RENA-00001',
        'serial_number' => 'A250705-510',
        'device_name_id' => DeviceName::create(['name' => 'AUTOCLAVE', 'slug' => 'autoclave'])->id,
        'customer_id' => Customer::create([
            'name' => 'Klinik Pratama Ocean Dental Radio Dalam',
            'slug' => 'klinik-pratama-ocean-dental-radio-dalam',
        ])->id,
    ]);

    $worksheet = CalibrationWorksheet::create([
        'device_id' => $device->id,
        'service_id' => $service->id,
        'template_key' => 'autoclave',
        'template_version' => '2024.1',
        'engine_version' => '1.0.0',
        'calibration_room' => 'STERILISASI',
        'received_date' => '2026-07-02',
        'calibration_date' => '2026-07-02',
        'technician_name' => 'MARSHEL ASYRAF DALTAFIKA',
        'work_method' => 'RKS/MT/IK.01-010',
        'device_resolution' => 0.1,
    ]);

    app(WorksheetHydrator::class)->hydrate($worksheet);

    CalibrationInstrument::create([
        'worksheet_id' => $worksheet->id,
        'role' => 'data_logger',
        'name' => 'High Temperature Data Logger',
        'correction_data' => [
            'u' => 0.036,
            'slope' => 1.0000817958739,
            'intercept' => -0.0074538762156067,
        ],
    ]);

    CalibrationInstrument::create([
        'worksheet_id' => $worksheet->id,
        'role' => 'stopwatch',
        'name' => 'Stopwatch',
        'correction_data' => ['u' => 0.029, 'slope' => 1.0, 'intercept' => 0.0],
    ]);

    $worksheet->physicalInspections()->update(['result' => true]);

    foreach ([1 => 0.118, 2 => 25.0, 3 => null, 4 => 100.0] as $index => $value) {
        $worksheet->electricalSafetyTests()->where('parameter_index', $index)->update(['raw_value' => $value]);
    }

    $temperature = $worksheet->performanceMeasurements()->where('parameter_index', 1)->first();
    $temperature->update(['setting_value' => 134.0]);
    $temperature->readings()->orderBy('sort_order')->get()->each(
        fn ($reading, $i) => $reading->update(['value' => [135.6, 135.7, 135.7][$i]])
    );

    $time = $worksheet->performanceMeasurements()->where('parameter_index', 2)->first();
    $time->update(['setting_value' => 3.0]);
    $time->readings()->update(['value' => 3.0]);

    return $worksheet->fresh();
}

it('reproduces the reference certificate score', function () {
    $result = makeReferenceWorksheet()->recalculate();

    // P29 = 6 baik / 6 * 10
    expect($result['physical_score'])->toBe(10.0)
        // W38 = 0 because parameter 3 was never measured
        ->and($result['electrical_score'])->toBe(0.0)
        // K70 sums only the temperature row (H72), the time row is dead weight
        ->and($result['performance_score'])->toBe(50.0)
        ->and($result['total_score'])->toBe(60.0)
        ->and($result['conclusion'])->toBe('Tidak Laik Pakai');
});

it('scores electrical only when all four parameters meet their threshold', function () {
    $worksheet = makeReferenceWorksheet();

    // Supply the missing insulation-leakage reading, threshold ≤ 0 µA.
    $worksheet->electricalSafetyTests()->where('parameter_index', 3)->update(['raw_value' => 0.0]);

    expect($worksheet->fresh()->recalculate()['electrical_score'])->toBe(40.0);
});

it('applies the analyzer certificate regression to raw electrical values', function () {
    $worksheet = makeReferenceWorksheet();

    CalibrationInstrument::create([
        'worksheet_id' => $worksheet->id,
        'role' => 'esa',
        'name' => 'Electrical Safety Analyzer',
        'correction_data' => ['slope' => 0.98773096942095, 'intercept' => -0.031262199089135],
    ]);

    $result = $worksheet->fresh()->recalculate();
    $test = collect($result['electrical']['parameters'])->firstWhere('index', 1);

    // N38 = J38 * slope + intercept = 0.118 * 0.98773... - 0.03126...
    expect($test['corrected_value'])->toEqualWithDelta(0.085290, 0.000001);
});

it('reproduces the workbook uncertainty budget for the time parameter', function () {
    $worksheet = makeReferenceWorksheet();
    $worksheet->recalculate();

    $budget = $worksheet->uncertaintyBudgets()->where('key', 'time')->first();

    // KETIDAKPASTIAN!K15/K17/K18 for the T-column budget.
    expect($budget->uc)->toEqualWithDelta(5.773472, 0.000001)
        ->and($budget->coverage_factor)->toEqualWithDelta(2.0086, 0.0001)
        ->and($budget->expanded_uncertainty)->toEqualWithDelta(11.59636, 0.00001);

    // The 134 °C budget: KETIDAKPASTIAN!K27/K28/K29/K30. The repeated-reading
    // component carries v = n-1 = 2 (G22 = D4 - 1), which sets veff ≈ 8.37.
    $temperature = $worksheet->uncertaintyBudgets()->where('key', 'temperature_134')->first();

    expect($temperature->uc)->toEqualWithDelta(0.04797685, 0.00000001)
        ->and($temperature->veff)->toEqualWithDelta(8.3707, 0.001);
});

it('derives performance statistics from the readings', function () {
    $worksheet = makeReferenceWorksheet();
    $worksheet->recalculate();

    $temperature = $worksheet->performanceMeasurements()->where('parameter_index', 1)->first();

    // U47 = AVERAGE(135.6,135.7,135.7) = 135.66667
    expect($temperature->mean)->toEqualWithDelta(135.6666667, 0.0000001)
        // X47 = STDEV (sample) = 0.05773503
        ->and($temperature->std_dev)->toEqualWithDelta(0.05773503, 0.00000001)
        // C51 = U47 * slope + intercept
        ->and($temperature->corrected_mean)->toEqualWithDelta(135.6703097640, 0.0000001)
        // F51 = C51 - 134
        ->and($temperature->correction)->toEqualWithDelta(1.6703097640, 0.0000001)
        ->and($temperature->result)->toBe('Lulus');
});

it('discards a performance parameter that fails its tolerance', function () {
    $worksheet = makeReferenceWorksheet();

    // Push the temperature mean 5 °C off the setting: |correction| + u95 > 2.
    $temperature = $worksheet->performanceMeasurements()->where('parameter_index', 1)->first();
    $temperature->readings()->orderBy('sort_order')->get()->each(
        fn ($reading) => $reading->update(['value' => 139.0])
    );

    $result = $worksheet->fresh()->recalculate();

    expect($result['performance_score'])->toBe(0.0)
        ->and($result['total_score'])->toBe(10.0)
        ->and($result['conclusion'])->toBe('Tidak Laik Pakai');
});

it('resolves symbolic divisors like the workbook', function () {
    $engine = new UncertaintyEngine;

    expect($engine->resolveDivisor('2', 3))->toBe(2.0)
        ->and($engine->resolveDivisor('sqrt(3)', 3))->toEqualWithDelta(1.7320508, 0.0000001)
        ->and($engine->resolveDivisor('sqrt(n)', 3))->toEqualWithDelta(1.7320508, 0.0000001)
        ->and($engine->resolveDivisor('1', 3))->toBe(1.0);
});
