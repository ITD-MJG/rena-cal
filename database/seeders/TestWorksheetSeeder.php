<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CalibrationElectricalSafetyTest;
use App\Models\CalibrationInstrument;
use App\Models\CalibrationPerformanceMeasurement;
use App\Models\CalibrationPhysicalInspection;
use App\Models\CalibrationWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceName;
use App\Models\Service;
use App\Models\Type;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $worksheet = CalibrationWorksheet::create([
            'device_id' => $device->id,
            'service_id' => $service->id,
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
            'conclusion' => 'Tidak Laik Pakai',
            'total_score' => 60.00,
        ]);

        // Instruments
        CalibrationInstrument::create([
            'worksheet_id' => $worksheet->id,
            'name' => 'Electrical Safety Analyzer',
            'brand' => 'RIGEL',
            'type' => '288 PLUS',
            'serial_number' => '17Q-1323',
            'traceability' => 'LK-032-IDN',
        ]);
        CalibrationInstrument::create([
            'worksheet_id' => $worksheet->id,
            'name' => 'Thermohygrometer',
            'brand' => '-',
            'type' => 'HTC-1',
            'serial_number' => 'RKS-THG-010',
            'traceability' => 'LK-385-IDN',
        ]);
        CalibrationInstrument::create([
            'worksheet_id' => $worksheet->id,
            'name' => 'High Temperature Data Logger',
            'brand' => 'MADGETECH',
            'type' => 'HiTemp140',
            'serial_number' => 'T53884',
            'traceability' => 'AC-2481',
        ]);

        // Physical inspections (6 fixed parameters)
        $inspections = [
            ['Badan dan permukaan', 'Selungkup utuh, bersih, terpasang ketat', true],
            ['Kotak Kontak Alat', 'Tidak ada gangguan pada kotak kontak', true],
            ['Kabel Catu Utama', 'Tidak ada kerusakan atau isolasi terkelupas', true],
            ['Sekering pengaman', 'Nilai tahanan dan tipe sesuai spesifikasi', true],
            ['Tombol, saklar dan control', 'Posisi kontrol sesuai', true],
            ['Tampilan dan indikator', 'Lampu indikator dan tampilan berfungsi', true],
        ];

        foreach ($inspections as $i => [$name, $desc, $result]) {
            CalibrationPhysicalInspection::create([
                'worksheet_id' => $worksheet->id,
                'parameter_index' => $i + 1,
                'parameter_name' => $name,
                'description' => $desc,
                'result' => $result,
            ]);
        }

        // Electrical safety tests
        $tests = [
            ['parameter_name' => 'Resistansi pembumian Kabel dapat dilepas (DPS)', 'measurement_method' => null, 'raw_value' => 0.118, 'unit' => 'Ω', 'certificate_correction' => -0.0327, 'threshold_operator' => '≤', 'threshold_value' => 0.3, 'threshold_unit' => 'Ω', 'result' => 'Memenuhi', 'weight' => 0],
            ['parameter_name' => 'Arus bocor peralatan metode Langsung kelas I tipe B', 'measurement_method' => 'Langsung', 'raw_value' => 25, 'unit' => 'µA', 'certificate_correction' => 5.5, 'threshold_operator' => '≤', 'threshold_value' => 500, 'threshold_unit' => 'µA', 'result' => 'Memenuhi', 'weight' => 0],
            ['parameter_name' => 'Resistansi Isolasi', 'measurement_method' => null, 'raw_value' => 100, 'unit' => 'MΩ', 'certificate_correction' => -5.95, 'threshold_operator' => '>', 'threshold_value' => 2, 'threshold_unit' => 'MΩ', 'result' => 'Memenuhi', 'weight' => 0],
        ];

        foreach ($tests as $test) {
            CalibrationElectricalSafetyTest::create(array_merge($test, ['worksheet_id' => $worksheet->id]));
        }

        // Performance measurements
        CalibrationPerformanceMeasurement::create([
            'worksheet_id' => $worksheet->id,
            'parameter_name' => 'Akurasi Temperatur',
            'setting_value' => 134,
            'setting_unit' => '°C',
            'measurement_1' => 135.6,
            'measurement_2' => 135.7,
            'measurement_3' => 135.7,
            'mean' => 135.6667,
            'std_dev' => 0.0577,
            'corrected_mean' => 135.6703,
            'correction' => 1.6703,
            'uncertainty_u95' => 0.0686,
            'total_correction_u95' => 0.0686,
            'allowed_deviation' => '± 2 °C',
            'tolerance' => 2.00,
            'result' => 'Lulus',
            'weight' => 50,
        ]);

        CalibrationPerformanceMeasurement::create([
            'worksheet_id' => $worksheet->id,
            'parameter_name' => 'Akurasi Waktu',
            'setting_value' => 3,
            'setting_unit' => 'menit',
            'measurement_1' => 3,
            'measurement_2' => 3,
            'measurement_3' => 3,
            'mean' => 3,
            'std_dev' => 0,
            'corrected_mean' => 3,
            'correction' => 0,
            'uncertainty_u95' => 0,
            'total_correction_u95' => 0,
            'allowed_deviation' => '≥ 3 menit',
            'tolerance' => 3.00,
            'result' => 'Lulus',
            'weight' => 50,
        ]);

        $this->command->info("Test worksheet created: ID {$worksheet->id}");
        $this->command->info("Preview at: /test-certificate/{$worksheet->id}");
    }
}
