<?php

namespace Database\Seeders;

use App\Calibration\WorksheetHydrator;
use App\Models\Brand;
use App\Models\CalibrationInstrument;
use App\Models\CalibrationWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceName;
use App\Models\Service;
use App\Models\Type;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reproduces AUTOCLAVE_A250705-510: the reference worksheet from
 * Autoclave.xlsx. Inputs are the raw cell values from LEMBAR KERJA; every
 * derived value is produced by the calculation engine, never seeded.
 */
class TestWorksheetSeeder extends Seeder
{
    public function run(): void
    {
        $service = Service::where('name', 'Instalasi sterilisasi pusat')->first();

        if (! $service) {
            $this->command->warn('No service found. Seed services first.');

            return;
        }

        $device = Device::firstOrCreate(
            ['device_number' => 'RENA-00001'],
            [
                'deviceId' => Str::uuid(),
                'serial_number' => 'A250705-510',
                'brand_id' => Brand::firstOrCreate(['name' => 'LOKAL'], ['slug' => 'lokal'])->id,
                'type_id' => Type::firstOrCreate(
                    ['name' => 'YA28X6T/8', 'slug' => 'ya28x6t-8'],
                    ['brand_id' => Brand::firstOrCreate(['name' => 'LOKAL'], ['slug' => 'lokal'])->id]
                )->id,
                'device_name_id' => DeviceName::firstOrCreate(['name' => 'AUTOCLAVE', 'slug' => 'autoclave'])->id,
                'customer_id' => Customer::firstOrCreate(
                    ['name' => 'Klinik Pratama Ocean Dental Radio Dalam', 'slug' => 'klinik-pratama-ocean-dental-radio-dalam']
                )->id,
                'calibration_date' => '2026-07-02',
                'next_calibration_date' => '2027-07-02',
                'result' => 'Tidak Laik Pakai',
            ]
        );

        // LEMBAR KERJA F11: device resolution 0.1 °C.
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
            'temperature_start' => 24.5,
            'temperature_end' => 24.6,
            'humidity_start' => 55.0,
            'humidity_end' => 56.0,
            'main_voltage' => 222.0,
            'device_resolution' => 0.1,
        ]);

        // Builds the six physical inspections, four electrical tests and two
        // performance parameters (with three readings each) from the template.
        app(WorksheetHydrator::class)->hydrate($worksheet);

        // Standard instruments. `role` binds each to the budget that consumes
        // its certificate. Regression data is SERT. DATA LOGGER!C33/C34 and
        // SERTIFIKAT ESA!J125/J126 for the insulation test.
        CalibrationInstrument::create([
            'worksheet_id' => $worksheet->id,
            'role' => 'esa',
            'name' => 'Electrical Safety Analyzer',
            'brand' => 'RIGEL',
            'type' => '288 PLUS',
            'serial_number' => '17Q-1323',
            'traceability' => 'LK-032-IDN',
            'correction_data' => ['slope' => 0.98773096942095, 'intercept' => -0.031262199089135],
        ]);
        CalibrationInstrument::create([
            'worksheet_id' => $worksheet->id,
            'role' => 'thermohygrometer',
            'name' => 'Thermohygrometer',
            'brand' => '-',
            'type' => 'HTC-1',
            'serial_number' => 'RKS-THG-010',
            'traceability' => 'LK-385-IDN',
        ]);
        CalibrationInstrument::create([
            'worksheet_id' => $worksheet->id,
            'role' => 'data_logger',
            'name' => 'High Temperature Data Logger',
            'brand' => 'MADGETECH',
            'type' => 'HiTemp140',
            'serial_number' => 'T53884',
            'traceability' => 'AC-2481',
            // SERT. DATA LOGGER!H15 = 0.036, C33/C34 regression.
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
            'brand' => '-',
            'type' => 'Digital',
            'serial_number' => 'RKS-SW-001',
            'traceability' => 'LK-100-IDN',
            // SERTIFIKAT STOPWATCH!K13 = 0.029.
            'correction_data' => ['u' => 0.029, 'slope' => 1.0, 'intercept' => 0.0],
        ]);

        // Physical inspections: all six "baik" (LEMBAR KERJA K29:N34).
        $worksheet->physicalInspections()->update(['result' => true]);

        // Electrical: P43=0.118 Ω, P44=25 µA, P45 unmeasured ("-"), P46=100 MΩ.
        // The unmeasured row is what makes W38 award zero — reproduced here.
        $electricalValues = [1 => 0.118, 2 => 25.0, 3 => null, 4 => 100.0];
        foreach ($electricalValues as $index => $value) {
            $worksheet->electricalSafetyTests()
                ->where('parameter_index', $index)
                ->update(['raw_value' => $value]);
        }

        // Performance: temperature setting 134 °C, readings 135.6/135.7/135.7
        // (LEMBAR KERJA M57/Q57/U57). Time readings are the setting, 3 minutes.
        $temperature = $worksheet->performanceMeasurements()->where('parameter_index', 1)->first();
        $temperature->update(['setting_value' => 134.0]);
        $temperature->readings()->orderBy('sort_order')->get()->each(
            fn ($reading, $i) => $reading->update(['value' => [135.6, 135.7, 135.7][$i]])
        );

        $time = $worksheet->performanceMeasurements()->where('parameter_index', 2)->first();
        $time->update(['setting_value' => 3.0]);
        $time->readings()->update(['value' => 3.0]);

        $result = $worksheet->recalculate();

        $this->command->info("Test worksheet created: ID {$worksheet->id}");
        $this->command->info(sprintf(
            'Fisik %s/10 · Listrik %s/40 · Kinerja %s/50 · Total %s/100 → %s',
            $result['physical_score'],
            $result['electrical_score'],
            $result['performance_score'],
            $result['total_score'],
            $result['conclusion'],
        ));
    }
}
