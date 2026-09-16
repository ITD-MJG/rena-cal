<?php

namespace App\Calibration;

/**
 * Autoclave (steam sterilizer) acceptance criteria.
 *
 * Values traced to Autoclave.xlsx: LEMBAR KERJA sections 4-6 and the
 * KETIDAKPASTIAN budget. See the class-level constants for the Tier 3
 * methodology assumptions that technicians may override.
 */
class AutoclaveTemplate implements WorksheetTemplate
{
    public function key(): string
    {
        return 'autoclave';
    }

    public function version(): string
    {
        return '2024.1';
    }

    public function label(): string
    {
        return 'Autoclave';
    }

    /**
     * LEMBAR KERJA rows 31-36.
     */
    public function physicalInspectionParameters(): array
    {
        return [
            [
                'index' => 1,
                'name' => 'Badan dan permukaan',
                'description' => 'Selungkup utuh, bersih, terpasang ketat satu dan lainnya dan tidak ada bekas tertimpa cairan ataupun gangguan lainnya.',
            ],
            [
                'index' => 2,
                'name' => 'Kotak Kontak Alat',
                'description' => 'Periksa apakah ada gangguan pada kotak kontak (AC-Power). Gerak-gerakkan kotak kontak untuk memastikan keamanannya. Goyang-goyangkan kotak kontak untuk memastikan tidak ada baut atau mur yang longgar.',
            ],
            [
                'index' => 3,
                'name' => 'Kabel Catu Utama',
                'description' => 'Periksa kabel, apakah terlihat ada kerusakan atau bagian isolasi yang terkelupas.',
            ],
            [
                'index' => 4,
                'name' => 'Sekering pengaman',
                'description' => 'Periksa sekering yang terdapat pada bagian luar rangkaian, apakah nilai tahanan dan tipenya sesuai dengan spesifikasi yang tertulis pada alat. Sekering pengaman harus berfungsi baik.',
            ],
            [
                'index' => 5,
                'name' => 'Tombol, saklar dan control',
                'description' => 'Sebelum mempergunakan/mengubah-ubah tombol kontrol, periksa posisi, jika terlihat tidak berada pada posisinya (periksa dengan menggunakan mode pemeriksaan standar). Bandingkan dengan posisi kontrol.',
            ],
            [
                'index' => 6,
                'name' => 'Tampilan dan indikator',
                'description' => 'Pastikan lampu indikator dan tampilan berfungsi seluruhnya.',
            ],
        ];
    }

    /**
     * LEMBAR KERJA rows 43-46, thresholds from the sheet's lookup tables.
     */
    public function electricalSafetyParameters(): array
    {
        return [
            [
                'index' => 1,
                'name' => 'Resistansi pembumian Kabel dapat dilepas (DPS)',
                'method' => null,
                'unit' => 'Ω',
                'threshold_operator' => '≤',
                'threshold_value' => 0.3,
                'threshold_unit' => 'Ω',
            ],
            [
                'index' => 2,
                'name' => 'Arus bocor peralatan metode Langsung kelas I tipe B',
                'method' => 'Langsung',
                'unit' => 'µA',
                'threshold_operator' => '≤',
                'threshold_value' => 500.0,
                'threshold_unit' => 'µA',
            ],
            [
                'index' => 3,
                'name' => 'Pengukuran arus bocor yang diaplikasikan tidak dilakukan (tidak ada bagian yang diaplikasikan)',
                'method' => null,
                'unit' => 'µA',
                'threshold_operator' => '≤',
                'threshold_value' => 0.0,
                'threshold_unit' => 'µA',
            ],
            [
                'index' => 4,
                'name' => 'Resistansi Isolasi',
                'method' => null,
                'unit' => 'MΩ',
                'threshold_operator' => '>',
                'threshold_value' => 2.0,
                'threshold_unit' => 'MΩ',
            ],
        ];
    }

    /**
     * LEMBAR KERJA rows 54-69. Two parameters, 50 points each.
     *
     * Only the temperature parameter is marked `scored`: PENGOLAHAN
     * DATA!K70 sums H70:H72 and H72 is the temperature row, so the time
     * accuracy score never reaches the certificate. Reproduced deliberately.
     */
    public function performanceParameters(): array
    {
        return [
            [
                'index' => 1,
                'name' => 'Akurasi Temperatur',
                'unit' => '°C',
                'setting_from' => 'temperature',
                'tolerance' => 2.0,
                'allowed_deviation' => '± 2 °C',
                'weight' => 50.0,
                'scored' => true,
                'correction_role' => 'data_logger',
            ],
            [
                'index' => 2,
                'name' => 'Akurasi Waktu',
                'unit' => 'menit',
                'setting_from' => 'sterilization_time',
                'tolerance' => 15.0,
                'allowed_deviation' => '≥ 15 Menit',
                'weight' => 50.0,
                'scored' => false,
                'correction_role' => 'stopwatch',
            ],
        ];
    }

    /**
     * KETIDAKPASTIAN sheet: rows 9-14 (temperature), 22-25 (temperature 134),
     * and the time budget at T9-T14.
     *
     * `divisor_expr` is symbolic so the engine resolves it:
     *   '2'        -> coverage factor of the certificate (k=2)
     *   'sqrt(3)'  -> rectangular distribution
     *   'sqrt(n)'  -> repeated readings
     *   '1'        -> normal, already a standard deviation
     */
    public function uncertaintyBudgets(): array
    {
        return [
            [
                'key' => 'temperature_121',
                'label' => 'Akurasi Temperature (121 °C)',
                'unit' => '°C',
                'instrument_role' => 'data_logger',
                'components' => [
                    [
                        'name' => 'Pembacaan berulang',
                        'symbol' => 'u_A',
                        'unit' => '°C',
                        'distribution' => 'normal',
                        'divisor_expr' => 'sqrt(n)',
                        'v' => null,
                        'c' => 1.0,
                        'source_type' => 'derived',
                        'source_ref' => 'performance.std_dev',
                    ],
                    [
                        'name' => 'sertifikat Standar',
                        'symbol' => 'u_cert',
                        'unit' => '°C',
                        'distribution' => 'normal',
                        'divisor_expr' => '2',
                        'v' => 60.0,
                        'c' => 1.0,
                        'source_type' => 'certificate',
                        'source_ref' => 'instrument.correction_data.u',
                    ],
                    [
                        'name' => 'Drift',
                        'symbol' => 'u_drift',
                        'unit' => '°C',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'constant',
                        'source_ref' => 'drift_value',
                    ],
                    [
                        'name' => 'resolusi UUT',
                        'symbol' => 'u_res',
                        'unit' => '°C',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'device_spec',
                        'source_ref' => 'device.resolution',
                    ],
                ],
            ],
            [
                'key' => 'temperature_134',
                'label' => 'Akurasi Temperature (134 °C)',
                'unit' => '°C',
                'instrument_role' => 'data_logger',
                'components' => [
                    [
                        'name' => 'Pembacaan berulang',
                        'symbol' => 'u_A',
                        'unit' => '°C',
                        'distribution' => 'normal',
                        'divisor_expr' => 'sqrt(n)',
                        'v' => null,
                        'c' => 1.0,
                        'source_type' => 'derived',
                        'source_ref' => 'performance.std_dev',
                    ],
                    [
                        'name' => 'sertifikat Standar',
                        'symbol' => 'u_cert',
                        'unit' => '°C',
                        'distribution' => 'normal',
                        'divisor_expr' => '2',
                        'v' => 60.0,
                        'c' => 1.0,
                        'source_type' => 'certificate',
                        'source_ref' => 'instrument.correction_data.u',
                    ],
                    [
                        'name' => 'Drift',
                        'symbol' => 'u_drift',
                        'unit' => '°C',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'constant',
                        'source_ref' => 'drift_value',
                    ],
                    [
                        'name' => 'resolusi UUT',
                        'symbol' => 'u_res',
                        'unit' => '°C',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'device_spec',
                        'source_ref' => 'device.resolution',
                    ],
                ],
            ],
            [
                'key' => 'time',
                'label' => 'Akurasi Waktu',
                'unit' => 'detik',
                'instrument_role' => 'stopwatch',
                'components' => [
                    [
                        'name' => 'Pembacaan berulang',
                        'symbol' => 'u_A',
                        'unit' => 'detik',
                        'distribution' => 'normal',
                        'divisor_expr' => 'sqrt(n)',
                        'v' => null,
                        'c' => 1.0,
                        'source_type' => 'derived',
                        'source_ref' => 'performance.std_dev',
                    ],
                    [
                        'name' => 'sertifikat Standar',
                        'symbol' => 'u_cert',
                        'unit' => 'detik',
                        'distribution' => 'normal',
                        'divisor_expr' => '2',
                        'v' => 60.0,
                        'c' => 1.0,
                        'source_type' => 'certificate',
                        'source_ref' => 'instrument.correction_data.u',
                    ],
                    [
                        'name' => 'Drift',
                        'symbol' => 'u_drift',
                        'unit' => 'detik',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'constant',
                        'source_ref' => 'time_drift_value',
                    ],
                    [
                        'name' => 'resolusi UUT',
                        'symbol' => 'u_res',
                        'unit' => 'detik',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'device_spec',
                        'source_ref' => 'time_resolution',
                    ],
                    [
                        'name' => 'Human Reaction Time bias',
                        'symbol' => 'u_hrb',
                        'unit' => 'detik',
                        'distribution' => 'segi4',
                        'divisor_expr' => 'sqrt(3)',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'constant',
                        'source_ref' => 'human_reaction_bias',
                    ],
                    [
                        'name' => 'Human Reaction Time Standar Deviasi',
                        'symbol' => 'u_hrsd',
                        'unit' => 'detik',
                        'distribution' => 'normal',
                        'divisor_expr' => '1',
                        'v' => 50.0,
                        'c' => 1.0,
                        'source_type' => 'constant',
                        'source_ref' => 'human_reaction_std_dev',
                    ],
                ],
            ],
        ];
    }

    /**
     * Tier 3 methodology constants. Defaults trace to the KETIDAKPASTIAN
     * sheet's hardcoded cells; technicians may override per worksheet with a
     * note.
     */
    public function constants(): array
    {
        return [
            // E12 / E24: drift = 10% of 0.1 °C
            'drift_percent' => 0.10,
            'drift_temperature_base' => 0.1,
            'drift_value' => 0.01,

            // W11: drift = 10% of 99.997685 s
            'time_drift_percent' => 0.10,
            'time_drift_base' => 99.997685,
            'time_drift_value' => 9.9997685,

            // V5: time resolution 0.01 s, so u = 0.5 * 0.01
            'time_resolution' => 0.01,

            // W13 / W14
            'human_reaction_bias' => 0.00693,
            'human_reaction_std_dev' => 0.03093,

            // LEMBAR KERJA rows 68-69: sterilization time by temperature
            'sterilization_time_121' => 15.0,
            'sterilization_time_134' => 3.0,
        ];
    }

    public function constant(string $key): ?float
    {
        return $this->constants()[$key] ?? null;
    }

    /**
     * LAPORAN HASIL B52 and PENGOLAHAN DATA O70 both use >= 90.
     * LEMBAR KERJA C75 uses >= 50 and is treated as the outlier.
     */
    public function conclusionThreshold(): float
    {
        return 90.0;
    }

    /**
     * PENGOLAHAN DATA!P29 = COUNTIF(baik)/COUNTA * 10.
     */
    public function physicalInspectionWeight(): float
    {
        return 10.0;
    }

    /**
     * PENGOLAHAN DATA!W38 = IF(COUNTIF(memenuhi) < 4, 0, 40).
     */
    public function electricalSafetyWeight(): float
    {
        return 40.0;
    }

    /**
     * Performance section total. The workbook sums only the temperature
     * parameter (H72 = IF(R65 < 50, 0, R65)); the time parameter is computed
     * but never added to K70. Time is therefore dead weight in the workbook.
     * Kept at 50 so the model reproduces the certificate, not the intent.
     */
    public function performanceWeight(): float
    {
        return 50.0;
    }

    /**
     * PENGOLAHAN DATA!W38: all four electrical parameters must pass for any
     * points to be awarded.
     */
    public function electricalSafetyRequiresAll(): bool
    {
        return true;
    }

    /**
     * PENGOLAHAN DATA!J65/J66: a parameter below 70 contributes zero.
     */
    public function performanceMinimumScore(): float
    {
        return 70.0;
    }

    /**
     * PENGOLAHAN DATA!H72: only the temperature parameter's full weighted
     * share is summed.
     */
    public function performanceRequiresFullCredit(): bool
    {
        return true;
    }

    /**
     * KETIDAKPASTIAN groups: temperature splits on the 121/134 °C setpoint,
     * time has a single budget.
     */
    public function uncertaintyBudgetKeyFor(array $parameter, ?float $setting): ?string
    {
        return match ($parameter['setting_from'] ?? null) {
            'temperature' => $setting !== null && $setting <= 121.0
                ? 'temperature_121'
                : 'temperature_134',
            'sterilization_time' => 'time',
            default => null,
        };
    }
}
