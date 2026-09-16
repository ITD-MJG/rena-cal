<?php

use App\Calibration\WorksheetHydrator;
use App\Models\CalibrationInstrument;
use App\Models\CalibrationWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceName;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The certificate is the only artefact the customer ever sees, so the PDF must
 * carry the same numbers the engine computed.
 */
function certifiedWorksheet(): CalibrationWorksheet
{
    $service = Service::create(['name' => 'Instalasi sterilisasi pusat', 'slug' => 'sterilisasi']);

    $device = Device::create([
        'deviceId' => Str::uuid(),
        'device_number' => 'RENA-00001',
        'order_number' => 'ORD-2026-0001',
        'cert_number' => 'RKS/01/2026',
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
        'range_min' => 0.1,
        'range_max' => 134.0,
        'range_unit' => '°C',
    ]);

    app(WorksheetHydrator::class)->hydrate($worksheet);

    CalibrationInstrument::create([
        'worksheet_id' => $worksheet->id,
        'role' => 'data_logger',
        'name' => 'High Temperature Data Logger',
        'brand' => 'MADGETECH',
        'type' => 'HiTemp140',
        'serial_number' => 'DL-001',
        'traceability' => 'LKS-2026-001',
        'correction_data' => [
            'u' => 0.036,
            'slope' => 1.0000817958739,
            'intercept' => -0.0074538762156067,
        ],
    ]);

    $worksheet->physicalInspections()->update(['result' => true]);

    $temperature = $worksheet->performanceMeasurements()->where('parameter_index', 1)->first();
    $temperature->update(['setting_value' => 134.0]);
    $temperature->readings()->orderBy('sort_order')->get()->each(
        fn ($reading, $i) => $reading->update(['value' => [135.6, 135.7, 135.7][$i]])
    );

    $worksheet->recalculate();

    return $worksheet->fresh();
}

function renderCertificate(CalibrationWorksheet $worksheet): string
{
    return view('pdf.certificate', [
        'worksheet' => $worksheet->load([
            'device.deviceName',
            'device.brand',
            'device.type',
            'device.customer',
            'service',
            'instruments',
            'physicalInspections',
            'electricalSafetyTests',
            'performanceMeasurements.readings',
            'uncertaintyBudgets.components',
        ]),
    ])->render();
}

it('renders every section of the calibration certificate', function () {
    $html = renderCertificate(certifiedWorksheet());

    expect($html)
        ->toContain('SERTIFIKAT KALIBRASI')
        ->toContain('RKS/01/2026')
        ->toContain('ORD-2026-0001')
        ->toContain('RENA-00001')
        ->toContain('A250705-510')
        ->toContain('Klinik Pratama Ocean Dental Radio Dalam')
        // Instrument table
        ->toContain('High Temperature Data Logger')
        ->toContain('MADGETECH')
        // Inspection + electrical sections
        ->toContain('PEMERIKSAAN KONDISI FISIK')
        ->toContain('PENGUKURAN KESELAMATAN LISTRIK')
        // Uncertainty budget plus its component breakdown
        ->toContain('KETIDAKPASTIAN PENGUKURAN')
        ->toContain('Rincian: Akurasi Temperature (134 °C)')
        // Verdict
        ->toContain('TIDAK LAIK PAKAI');
});

it('prints the engine numbers rather than blanks', function () {
    $worksheet = certifiedWorksheet();
    $html = renderCertificate($worksheet);

    $budget = $worksheet->uncertaintyBudgets()->where('key', 'temperature_134')->first();

    // The mean is derived from the readings, not stored by hand.
    expect($html)
        ->toContain(number_format((float) $budget->expanded_uncertainty, 4))
        ->toContain(number_format((float) $budget->uc, 6))
        // Resolusi now reads the worksheet, not a device column that no longer exists.
        ->toContain('Resolusi')
        ->not->toContain('0.1 s/d 134');
});

it('produces a downloadable pdf', function () {
    $worksheet = certifiedWorksheet();

    $pdf = Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet->load([
        'device.deviceName',
        'device.brand',
        'device.type',
        'device.customer',
        'service',
        'instruments',
        'physicalInspections',
        'electricalSafetyTests',
        'performanceMeasurements.readings',
        'uncertaintyBudgets.components',
    ])])->setPaper('a4', 'portrait');

    $output = $pdf->output();

    expect($output)->toStartWith('%PDF-')
        ->and(strlen($output))->toBeGreaterThan(10_000);
});
