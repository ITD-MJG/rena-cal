<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat Kalibrasi - {{ $worksheet->device->device_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; color: #333; }
        .page { width: 210mm; min-height: 297mm; padding: 15mm 20mm; page-break-after: always; position: relative; }
        .page:last-child { page-break-after: avoid; }

        /* Header */
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #003366; padding-bottom: 10px; }
        .header h1 { font-size: 14pt; color: #003366; margin-bottom: 5px; }
        .header p { font-size: 8pt; color: #666; }

        /* Cover page */
        .cover-content { margin-top: 30px; }
        .doc-number { text-align: right; font-size: 9pt; color: #666; margin-bottom: 20px; }
        .qr-placeholder { width: 100px; height: 100px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #999; font-size: 8pt; }
        .device-info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .device-info-table td { padding: 8px 12px; border: 1px solid #ddd; }
        .device-info-table .label { background: #f5f5f5; font-weight: bold; width: 30%; }
        .result-badge { display: inline-block; padding: 8px 20px; font-size: 14pt; font-weight: bold; border-radius: 4px; margin: 20px 0; }
        .result-laik { background: #d4edda; color: #155724; border: 2px solid #28a745; }
        .result-tidak-laik { background: #f8d7da; color: #721c24; border: 2px solid #dc3545; }
        .customer-info { margin: 30px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #003366; }
        .signatures { display: flex; justify-content: space-between; margin-top: 60px; }
        .signature-box { text-align: center; width: 45%; }
        .signature-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; }

        /* Technical pages */
        .section { margin-bottom: 15px; }
        .section-title { font-size: 11pt; font-weight: bold; color: #003366; margin-bottom: 8px; padding-bottom: 3px; border-bottom: 1px solid #003366; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 20px; }
        .info-item { display: flex; }
        .info-label { font-weight: bold; min-width: 120px; }
        table.data-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9pt; }
        table.data-table th, table.data-table td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        table.data-table th { background: #003366; color: white; font-weight: bold; }
        table.data-table tr:nth-child(even) { background: #f9f9f9; }
        .result-pass { color: #28a745; font-weight: bold; }
        .result-fail { color: #dc3545; font-weight: bold; }

        /* Footer */
        .page-footer { position: absolute; bottom: 15mm; left: 20mm; right: 20mm; text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #ddd; padding-top: 5px; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 1: COVER --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="header">
        <h1>PT RENA KALIBRINDO SELARAS</h1>
        <p>Jl. Pangeran Antasari No.4, Rt.074 Rw.07, Cipete Selatan, Kecamatan Cilandak, Jakarta Selatan — 12150</p>
        <p>Email: admin@rena.co.id</p>
    </div>

    <div class="doc-number">
        No. {{ $worksheet->cert_number ?? 'RKS/XX/XXXX' }}
    </div>

    <div class="cover-content">
        {{-- QR Code Placeholder --}}
        <div class="qr-placeholder">
            [QR CODE]
        </div>

        {{-- Device Info --}}
        <table class="device-info-table">
            <tr>
                <td class="label">Jenis Alat</td>
                <td>{{ $worksheet->device->deviceName->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Merk</td>
                <td>{{ $worksheet->device->brand->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Tipe</td>
                <td>{{ $worksheet->device->type->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Nomor Seri</td>
                <td>{{ $worksheet->device->serial_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Kalibrasi</td>
                <td>{{ $worksheet->calibration_date->format('d M Y') }}</td>
            </tr>
            <tr>
                <td class="label">Kalibrasi Berikutnya</td>
                <td>{{ $worksheet->calibration_date->addYear()->format('d M Y') }}</td>
            </tr>
        </table>

        {{-- Result Badge --}}
        <div style="text-align: center;">
            <span class="result-badge {{ $worksheet->conclusion === 'Laik Pakai' ? 'result-laik' : 'result-tidak-laik' }}">
                {{ strtoupper($worksheet->conclusion) }}
            </span>
        </div>

        {{-- Customer Info --}}
        <div class="customer-info">
            <strong>{{ $worksheet->device->customer->name ?? '—' }}</strong><br>
            {{ $worksheet->device->customer->address ?? '—' }}
        </div>

        {{-- Signatures --}}
        <div style="text-align: right; margin-top: 30px;">
            Jakarta, {{ $worksheet->calibration_date->addDay()->format('d M Y') }}
        </div>
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">Penanggung Jawab</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Direktur</div>
            </div>
        </div>
    </div>

    <div class="page-footer">Halaman 1 dari 3</div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 2: TECHNICAL DETAILS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="header" style="margin-bottom: 15px;">
        <h1 style="font-size: 12pt;">PT RENA KALIBRINDO SELARAS</h1>
    </div>

    {{-- 1. Identitas Alat --}}
    <div class="section">
        <div class="section-title">1. IDENTITAS ALAT</div>
        <div class="info-grid">
            <div class="info-item"><span class="info-label">Merk:</span> {{ $worksheet->device->brand->name ?? '—' }}</div>
            <div class="info-item"><span class="info-label">Tipe:</span> {{ $worksheet->device->type->name ?? '—' }}</div>
            <div class="info-item"><span class="info-label">No. Seri:</span> {{ $worksheet->device->serial_number ?? '—' }}</div>
            <div class="info-item"><span class="info-label">Resolusi:</span> {{ $worksheet->device->resolution ?? '—' }} {{ $worksheet->device->resolution_unit ?? '°C' }}</div>
            <div class="info-item"><span class="info-label">Rentang Ukur:</span> {{ $worksheet->device->range_min ?? '—' }} s/d {{ $worksheet->device->range_max ?? '—' }} {{ $worksheet->device->range_unit ?? '°C' }}</div>
        </div>
    </div>

    {{-- 2. Pelaksanaan Kalibrasi --}}
    <div class="section">
        <div class="section-title">2. PELAKSANAAN KALIBRASI</div>
        <div class="info-grid">
            <div class="info-item"><span class="info-label">Nama Pelayanan:</span> {{ $worksheet->service->name ?? '—' }}</div>
            <div class="info-item"><span class="info-label">Ruangan Kalibrasi:</span> {{ $worksheet->calibration_room ?? '—' }}</div>
            <div class="info-item"><span class="info-label">Pelaksana Teknis:</span> {{ $worksheet->technician_name ?? '—' }}</div>
            <div class="info-item"><span class="info-label">Metode Kerja:</span> {{ $worksheet->work_method ?? '—' }}</div>
        </div>
    </div>

    {{-- 3. Kondisi Lingkungan --}}
    <div class="section">
        <div class="section-title">3. KONDISI LINGKUNGAN</div>
        <table class="data-table">
            <thead>
                <tr><th>Parameter</th><th>Awal</th><th>Akhir</th><th>Toleransi</th><th>Hasil</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Suhu Ruangan (°C)</td>
                    <td>{{ $worksheet->temperature_start ?? '—' }}</td>
                    <td>{{ $worksheet->temperature_end ?? '—' }}</td>
                    <td>10°C s/d 40°C</td>
                    <td class="result-pass">Lulus</td>
                </tr>
                <tr>
                    <td>Kelembaban Ruangan (%RH)</td>
                    <td>{{ $worksheet->humidity_start ?? '—' }}</td>
                    <td>{{ $worksheet->humidity_end ?? '—' }}</td>
                    <td>15%RH s/d 85%RH</td>
                    <td class="result-pass">Lulus</td>
                </tr>
                <tr>
                    <td>Tegangan Utama (Vac)</td>
                    <td colspan="2">{{ $worksheet->main_voltage ?? '—' }}</td>
                    <td>—</td>
                    <td class="result-pass">Lulus</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- 4. Daftar Alat Ukur --}}
    <div class="section">
        <div class="section-title">4. DAFTAR ALAT YANG DIGUNAKAN</div>
        <table class="data-table">
            <thead>
                <tr><th>No</th><th>Nama Alat</th><th>Merk</th><th>Tipe</th><th>No. Seri</th><th>Ketertelusuran</th></tr>
            </thead>
            <tbody>
                @forelse($worksheet->instruments as $i => $instrument)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $instrument->name }}</td>
                    <td>{{ $instrument->brand ?? '—' }}</td>
                    <td>{{ $instrument->type ?? '—' }}</td>
                    <td>{{ $instrument->serial_number ?? '—' }}</td>
                    <td>{{ $instrument->traceability ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align: center;">Tidak ada alat ukur tercatat</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 5. Pemeriksaan Fisik --}}
    <div class="section">
        <div class="section-title">5. PEMERIKSAAN KONDISI FISIK DAN FUNGSI (CEK KUALITATIF)</div>
        <table class="data-table">
            <thead>
                <tr><th>No</th><th>Parameter</th><th>Hasil</th></tr>
            </thead>
            <tbody>
                @foreach($worksheet->physicalInspections as $i => $inspection)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $inspection->parameter_name }}</td>
                    <td class="{{ $inspection->result ? 'result-pass' : 'result-fail' }}">
                        {{ $inspection->result ? 'Baik' : 'Tidak Baik' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- 6. Pengukuran Keselamatan Listrik --}}
    <div class="section">
        <div class="section-title">6. PENGUKURAN KESELAMATAN LISTRIK</div>
        <table class="data-table">
            <thead>
                <tr><th>No</th><th>Parameter</th><th>Terukur</th><th>Ambang Batas</th><th>Hasil</th></tr>
            </thead>
            <tbody>
                @foreach($worksheet->electricalSafetyTests as $i => $test)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $test->parameter_name }}</td>
                    <td>{{ $test->corrected_value ?? $test->raw_value }} {{ $test->unit }}</td>
                    <td>{{ $test->threshold_operator }} {{ $test->threshold_value }} {{ $test->threshold_unit }}</td>
                    <td class="{{ $test->result === 'Memenuhi' ? 'result-pass' : 'result-fail' }}">
                        {{ $test->result }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="page-footer">Halaman 2 dari 3</div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 3: PERFORMANCE, CONCLUSION, NOTES --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="header" style="margin-bottom: 15px;">
        <h1 style="font-size: 12pt;">PT RENA KALIBRINDO SELARAS</h1>
    </div>

    {{-- 7. Hasil Kalibrasi --}}
    <div class="section">
        <div class="section-title">7. HASIL KALIBRASI</div>

        {{-- Temperature Accuracy --}}
        <div style="margin-bottom: 15px;">
            <strong>1. Akurasi Temperatur</strong>
            <table class="data-table" style="margin-top: 5px;">
                <thead>
                    <tr><th>Setting Alat (°C)</th><th>Penunjukan Standar (°C)</th><th>Koreksi (°C)</th><th>Ambang Batas</th><th>Hasil</th></tr>
                </thead>
                <tbody>
                    @foreach($worksheet->performanceMeasurements->where('setting_unit', '°C') as $measurement)
                    <tr>
                        <td>{{ $measurement->setting_value }}</td>
                        <td>{{ $measurement->corrected_mean ?? $measurement->mean ?? '—' }}</td>
                        <td>{{ $measurement->correction ?? '—' }}</td>
                        <td>± {{ $measurement->tolerance }} {{ $measurement->setting_unit }}</td>
                        <td class="{{ $measurement->result === 'Lulus' ? 'result-pass' : 'result-fail' }}">
                            {{ $measurement->result }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="font-size: 9pt; margin-top: 5px;">
                Ketidakpastian Pengukuran: ± {{ $worksheet->performanceMeasurements->firstWhere('setting_unit', '°C')?->total_correction_u95 ? number_format($worksheet->performanceMeasurements->firstWhere('setting_unit', '°C')->total_correction_u95, 2) : '—' }} °C
            </div>
        </div>

        {{-- Time Accuracy --}}
        <div>
            <strong>2. Akurasi Waktu</strong>
            <table class="data-table" style="margin-top: 5px;">
                <thead>
                    <tr><th>Setting Alat (Menit)</th><th>Penunjukan Standar (Menit)</th><th>Koreksi (Menit)</th><th>Ambang Batas</th><th>Hasil</th></tr>
                </thead>
                <tbody>
                    @foreach($worksheet->performanceMeasurements->where('setting_unit', 'menit') as $measurement)
                    <tr>
                        <td>{{ $measurement->setting_value }}</td>
                        <td>{{ $measurement->corrected_mean ?? $measurement->mean ?? '—' }}</td>
                        <td>{{ $measurement->correction ?? '—' }}</td>
                        <td>≥ {{ $measurement->tolerance }} {{ $measurement->setting_unit }}</td>
                        <td class="{{ $measurement->result === 'Lulus' ? 'result-pass' : 'result-fail' }}">
                            {{ $measurement->result }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="font-size: 9pt; margin-top: 5px;">
                Ketidakpastian Pengukuran: ± {{ $worksheet->performanceMeasurements->firstWhere('setting_unit', 'menit')?->total_correction_u95 ? number_format($worksheet->performanceMeasurements->firstWhere('setting_unit', 'menit')->total_correction_u95, 2) : '—' }} menit
            </div>
        </div>
    </div>

    {{-- 8. Kesimpulan --}}
    <div class="section" style="margin-top: 25px;">
        <div class="section-title">8. KESIMPULAN</div>
        <div style="text-align: center; padding: 15px;">
            <span style="font-size: 14pt; font-weight: bold; {{ $worksheet->conclusion === 'Laik Pakai' ? 'color: #28a745;' : 'color: #dc3545;' }}">
                DINYATAKAN {{ strtoupper($worksheet->conclusion) }}
            </span>
        </div>
    </div>

    {{-- 9. Keterangan --}}
    <div class="section">
        <div class="section-title">9. KETERANGAN</div>
        <ol style="padding-left: 20px; font-size: 9pt;">
            <li>Kalibrasi menggunakan Instruksi Kerja (RKS/MT/IK.01-010) yang mengacu ke Keputusan Direktur Jendral Pelayanan Kesehatan Nomor: HK.02.02/D/43649/2024, Metode Kerja Pengujian dan Kalibrasi Alat Kesehatan, Kementrian Kesehatan RI</li>
            <li>Nilai Ketidakpastian pengukuran mempunyai tingkat kepercayaan 95% dengan factor cakupan k = 2</li>
            <li>Hasil pengujian dan kalibrasi hanya terkait dengan nomor seri di atas</li>
            <li>Nilai sebenarnya adalah nilai penunjukan alat ditambah dengan nilai koreksi</li>
        </ol>
    </div>

    <div class="page-footer">Halaman 3 dari 3</div>
</div>

</body>
</html>
