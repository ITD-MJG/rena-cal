<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Kalibrasi - {{ $worksheet->device->device_number }}</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; }
        .page { width: 170mm; padding: 10mm 20mm; page-break-after: always; }
        .page:last-child { page-break-after: avoid; }

        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 4px 6px; vertical-align: top; }
        .border-box td { border: 1px solid #000; }
        .border-thin td { border: 0.5px solid #999; }

        .header-bar { background: #003366; color: #fff; padding: 6px 10px; font-size: 8pt; }
        .header-bar td { border: none !important; color: #fff; padding: 2px 8px; }

        .section-title { font-weight: bold; font-size: 10pt; color: #003366; padding: 6px 0 3px 0; border-bottom: 1.5px solid #003366; margin-bottom: 6px; }

        .label-cell { background: #f0f0f0; font-weight: bold; width: 35%; font-size: 8.5pt; }
        .value-cell { font-size: 8.5pt; }

        .badge { display: inline-block; padding: 4px 16px; font-size: 12pt; font-weight: bold; }
        .badge-laik { background: #d4edda; color: #155724; border: 2px solid #28a745; }
        .badge-tidak { background: #f8d7da; color: #721c24; border: 2px solid #dc3545; }

        .small { font-size: 7.5pt; color: #666; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }

        .sig-table td { border: none !important; padding-top: 40px; }
        .sig-line { border-top: 1px solid #000; padding-top: 4px; text-align: center; font-size: 8pt; }

        .data-table th { background: #003366; color: #fff; font-size: 8pt; padding: 3px 5px; text-align: center; }
        .data-table td { border: 0.5px solid #ccc; font-size: 8pt; padding: 3px 5px; text-align: center; }
        .data-table td.text-left { text-align: left; }
        .data-table tr:nth-child(even) td { background: #f8f8f8; }

        .pass { color: #155724; font-weight: bold; }
        .fail { color: #721c24; font-weight: bold; }

        .page-num { text-align: center; font-size: 7pt; color: #000; padding-top: 5mm; border-top: 0.5px solid #ddd; margin-top: 5mm; }
        .page-1 { font-size: 12pt; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 1: COVER --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- Page 1 layout based on PDF reference:
- Header: logo left, company name/address center-right
- Title: SERTIFIKAT KALIBRASI
- Doc numbers on left
- Two columns: IDENTITAS ALAT (left) | IDENTITAS PEMILIK (right)
- Dates section
- Signatures with QR placeholder
- Footer disclaimer
*--}}
<div class="page page-1">

    @include('pdf.partials.header')

    {{-- Title --}}
    <div style="text-align:center; font-size: 16pt; font-weight:bold; margin-bottom: 15px;">SERTIFIKAT KALIBRASI</div>

    {{-- Document Numbers --}}
    <table style="margin-bottom: 12px;">
        <tr>
            <td style="border:none; font-weight:bold; width:120px;">Nomor Sertifikat</td>
            <td style="border:none; width:15px;">:</td>
            <td style="border:none; font-weight:bold;">{{ $worksheet->cert_number ?? 'RKS/XX/XXXX' }}</td>
        </tr>
        <tr>
            <td style="border:none; font-weight:bold;">Nomor Pesanan</td>
            <td style="border:none;">:</td>
            <td style="border:none; font-weight:bold;">{{ $worksheet->order_number ?? '—' }}</td>
        </tr>
    </table>

    {{-- Two-Column: Identitas Alat + Identitas Pemilik --}}
    <table style="margin-bottom: 12px;">
        <tr>
            {{-- Left: Identitas Alat --}}
            <td style="border:none; width:50%; vertical-align:top; padding-right:10px;">
                <div style="font-weight:bold; font-size:10pt; border-bottom: 2px solid #003366; padding-bottom:3px; margin-bottom:8px;">IDENTITAS ALAT</div>
                <table style="width:100%;">
                    <tr>
                        <td style="border:none; font-weight:bold; width:100px; padding:3px 0;">Nama Alat :</td>
                        <td style="border:none; padding:3px 0;">{{ $worksheet->device->deviceName->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="border:none; font-weight:bold; padding:3px 0;">Merek :</td>
                        <td style="border:none; padding:3px 0;">{{ $worksheet->device->brand->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="border:none; font-weight:bold; padding:3px 0;">Tipe :</td>
                        <td style="border:none; padding:3px 0;">{{ $worksheet->device->type->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="border:none; font-weight:bold; padding:3px 0;">Nomor Seri :</td>
                        <td style="border:none; padding:3px 0;">{{ $worksheet->device->serial_number ?? '—' }}</td>
                    </tr>
                </table>
            </td>
            {{-- Right: Identitas Pemilik --}}
            <td style="border:none; width:50%; vertical-align:top; padding-left:10px;">
                <div style="font-weight:bold; font-size:10pt; border-bottom: 2px solid #003366; padding-bottom:3px; margin-bottom:8px;">IDENTITAS PEMILIK</div>
                <div style="margin-bottom:5px;"><strong>Nama Pemilik :</strong></div>
                <div style="margin-bottom:8px;">{{ $worksheet->device->customer->name ?? '—' }}</div>
                <div style="margin-bottom:5px;"><strong>Alamat Pemilik :</strong></div>
                <div>{{ $worksheet->device->customer->address ?? '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- Dates Section --}}
    <table style="margin-top: 15px; width:75%;">
        <tr>
            <td style="border:none; font-weight:bold; width:140px; padding:4px 0;">Tanggal Penerimaan</td>
            <td style="border:none; width:15px; padding:4px 0;">:</td>
            <td style="border:none; padding:4px 0;">{{ strtoupper($worksheet->received_date?->format('d M Y') ?? '—') }}</td>
        </tr>
        <tr>
            <td style="border:none; font-weight:bold; padding:4px 0;">Tanggal Kalibrasi</td>
            <td style="border:none; padding:4px 0;">:</td>
            <td style="border:none; padding:4px 0;">{{ strtoupper($worksheet->calibration_date?->format('d M Y') ?? '—') }}</td>
        </tr>
        <tr>
            <td style="border:none; font-weight:bold; padding:4px 0;">Hasil Kalibrasi</td>
            <td style="border:none; padding:4px 0;">:</td>
            <td style="border:none; padding:4px 0; font-weight:bold; {{ ($worksheet->conclusion ?? '') === 'Laik Pakai' ? 'color:#155724;' : 'color:#721c24;' }}">{{ strtoupper($worksheet->conclusion ?? 'TIDAK LAIK PAKAI') }}</td>
        </tr>
        <tr>
            <td style="border:none; font-weight:bold; padding:4px 0;">Berlaku Sampai</td>
            <td style="border:none; padding:4px 0;">:</td>
            <td style="border:none; padding:4px 0;">{{ strtoupper($worksheet->calibration_date?->addYear()->format('d M Y') ?? '—') }}</td>
        </tr>
    </table>

    {{-- Signatures + QR --}}
    <table style="margin-top: 30px;">
        <tr>
            <td style="border:none; width:50%;"></td>
            <td style="border:none; width:50%; text-align:center;">
                <div style="margin-bottom: 5px;">Jakarta, {{ $worksheet->calibration_date?->addDay()->format('d M Y') ?? '—' }}</div>
                <div style="font-weight:bold; text-decoration:underline; margin-bottom: 5px;">Penanggung Jawab</div>
            </td>
        </tr>
        <tr>
            <td style="border:none; width:50%; text-align:right; padding-right:5px; vertical-align:middle;">
                <div style="font-size:8pt; font-style:italic; color:#000; line-height:1.3;">Dokumen ini telah ditandatangani<br>secara elektronik menggunakan<br>Sertifikat Elektronik yang<br>diterbitkan oleh Mekari</div>
            </td>
            <td style="border:none; width:50%; text-align:center;">
                <div style="border:2px dashed #ccc; width:80px; height:80px; text-align:center; vertical-align:middle; color:#999; font-size:7pt; line-height:80px; margin:0 auto 5px auto;">[QR CODE]</div>
                <div style="font-weight:bold; text-decoration:underline;">Direktur</div>
            </td>
        </tr>
    </table>

    @include('pdf.partials.footer', ['pageNumber' => 1])
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 2: TECHNICAL DETAILS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page page-1">

    @include('pdf.partials.header')

    {{-- 1. Identitas Alat --}}
    <div class="section-title">1. IDENTITAS ALAT</div>
    <table class="border-thin" style="margin-bottom: 10px;">
        <tr>
            <td class="label-cell" style="width:25%;">Merk</td>
            <td style="width:25%;">{{ $worksheet->device->brand->name ?? '—' }}</td>
            <td class="label-cell" style="width:25%;">Tipe</td>
            <td style="width:25%;">{{ $worksheet->device->type->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label-cell">No. Seri</td>
            <td>{{ $worksheet->device->serial_number ?? '—' }}</td>
            <td class="label-cell">Resolusi</td>
            <td>{{ $worksheet->device->resolution ?? '0.1' }} {{ $worksheet->device->resolution_unit ?? '°C' }}</td>
        </tr>
        <tr>
            <td class="label-cell">Rentang Ukur</td>
            <td colspan="3">{{ $worksheet->device->range_min ?? '0.1' }} s/d {{ $worksheet->device->range_max ?? '134' }} {{ $worksheet->device->range_unit ?? '°C' }}</td>
        </tr>
    </table>

    {{-- 2. Pelaksanaan Kalibrasi --}}
    <div class="section-title">2. PELAKSANAAN KALIBRASI</div>
    <table class="border-thin" style="margin-bottom: 10px;">
        <tr>
            <td class="label-cell" style="width:25%;">Nama Pelayanan</td>
            <td colspan="3">{{ $worksheet->service->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label-cell">Ruangan Kalibrasi</td>
            <td>{{ $worksheet->calibration_room ?? '—' }}</td>
            <td class="label-cell">Metode Kerja</td>
            <td>{{ $worksheet->work_method ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label-cell">Pelaksana Teknis</td>
            <td colspan="3">{{ $worksheet->technician_name ?? '—' }}</td>
        </tr>
    </table>

    {{-- 3. Kondisi Lingkungan --}}
    <div class="section-title">3. KONDISI LINGKUNGAN</div>
    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr><th style="width:35%;" class="text-left">Parameter</th><th>Awal</th><th>Akhir</th><th>Toleransi</th><th>Hasil</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-left">Suhu Ruangan (°C)</td>
                <td>{{ $worksheet->temperature_start ?? '—' }}</td>
                <td>{{ $worksheet->temperature_end ?? '—' }}</td>
                <td>10°C s/d 40°C</td>
                <td class="pass">Lulus</td>
            </tr>
            <tr>
                <td class="text-left">Kelembaban Ruangan (%RH)</td>
                <td>{{ $worksheet->humidity_start ?? '—' }}</td>
                <td>{{ $worksheet->humidity_end ?? '—' }}</td>
                <td>15%RH s/d 85%RH</td>
                <td class="pass">Lulus</td>
            </tr>
            <tr>
                <td class="text-left">Tegangan Utama (Vac)</td>
                <td colspan="2">{{ $worksheet->main_voltage ?? '—' }}</td>
                <td>—</td>
                <td class="pass">Lulus</td>
            </tr>
        </tbody>
    </table>

    {{-- 4. Daftar Alat Ukur --}}
    <div class="section-title">4. DAFTAR ALAT YANG DIGUNAKAN</div>
    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr><th style="width:4%;">No</th><th style="width:25%;">Nama Alat</th><th style="width:15%;">Merk</th><th style="width:15%;">Tipe</th><th style="width:18%;">No. Seri</th><th style="width:23%;">Ketertelusuran</th></tr>
        </thead>
        <tbody>
            @forelse($worksheet->instruments as $i => $instrument)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td class="text-left">{{ $instrument->name }}</td>
                <td>{{ $instrument->brand ?? '—' }}</td>
                <td>{{ $instrument->type ?? '—' }}</td>
                <td>{{ $instrument->serial_number ?? '—' }}</td>
                <td>{{ $instrument->traceability ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="center">Tidak ada alat ukur tercatat</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 5. Pemeriksaan Fisik --}}
    <div class="section-title">5. PEMERIKSAAN KONDISI FISIK DAN FUNGSI (CEK KUALITATIF)</div>
    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr><th style="width:5%;">No</th><th style="width:70%;">Parameter</th><th style="width:25%;">Hasil</th></tr>
        </thead>
        <tbody>
            @foreach($worksheet->physicalInspections as $i => $inspection)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $inspection->parameter_name }}</td>
                <td class="{{ $inspection->result ? 'pass' : 'fail' }}">{{ $inspection->result ? 'Baik' : 'Tidak Baik' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 6. Pengukuran Keselamatan Listrik --}}
    <div class="section-title">6. PENGUKURAN KESELAMATAN LISTRIK</div>
    <table class="data-table">
        <thead>
            <tr><th style="width:5%;">No</th><th style="width:40%;" class="text-left">Parameter</th><th style="width:15%;">Terukur</th><th style="width:25%;">Ambang Batas</th><th style="width:15%;">Hasil</th></tr>
        </thead>
        <tbody>
            @foreach($worksheet->electricalSafetyTests as $i => $test)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td class="text-left">{{ $test->parameter_name }}</td>
                <td>{{ $test->corrected_value ?? $test->raw_value }} {{ $test->unit }}</td>
                <td>{{ $test->threshold_operator }} {{ $test->threshold_value }} {{ $test->threshold_unit }}</td>
                <td class="{{ $test->result === 'Memenuhi' ? 'pass' : 'fail' }}">{{ $test->result }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @include('pdf.partials.footer', ['pageNumber' => 2])
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 3: PERFORMANCE, CONCLUSION, NOTES --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page page-1">

    @include('pdf.partials.header')

    {{-- 7. Hasil Kalibrasi --}}
    <div class="section-title">7. HASIL KALIBRASI</div>

    {{-- Temperature --}}
    <div class="bold" style="margin-bottom:4px;">1. Akurasi Temperatur</div>
    <table class="data-table" style="margin-bottom: 4px;">
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
                <td class="{{ $measurement->result === 'Lulus' ? 'pass' : 'fail' }}">{{ $measurement->result }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="small" style="margin-bottom: 10px;">
        Ketidakpastian Pengukuran: ± {{ number_format($worksheet->performanceMeasurements->firstWhere('setting_unit', '°C')?->total_correction_u95 ?? 0, 2) }} °C
    </div>

    {{-- Time --}}
    <div class="bold" style="margin-bottom:4px;">2. Akurasi Waktu</div>
    <table class="data-table" style="margin-bottom: 4px;">
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
                <td class="{{ $measurement->result === 'Lulus' ? 'pass' : 'fail' }}">{{ $measurement->result }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="small" style="margin-bottom: 15px;">
        Ketidakpastian Pengukuran: ± {{ number_format($worksheet->performanceMeasurements->firstWhere('setting_unit', 'menit')?->total_correction_u95 ?? 0, 2) }} menit
    </div>

    {{-- 8. Kesimpulan --}}
    <div class="section-title">8. KESIMPULAN</div>
    <table style="margin: 10px 0;">
        <tr>
            <td style="border:none; text-align:center; padding: 10px;">
                <span style="font-size: 14pt; font-weight:bold; {{ ($worksheet->conclusion ?? '') === 'Laik Pakai' ? 'color:#155724;' : 'color:#721c24;' }}">
                    DINYATAKAN {{ strtoupper($worksheet->conclusion ?? 'TIDAK LAIK PAKAI') }}
                </span>
            </td>
        </tr>
    </table>

    {{-- 9. Keterangan --}}
    <div class="section-title">9. KETERANGAN</div>
    <table>
        <tr><td style="border:none; vertical-align:top; width:20px;">1.</td><td style="border:none; font-size:8pt;">Kalibrasi menggunakan Instruksi Kerja (RKS/MT/IK.01-010) yang mengacu ke Keputusan Direktur Jendral Pelayanan Kesehatan Nomor: HK.02.02/D/43649/2024, Metode Kerja Pengujian dan Kalibrasi Alat Kesehatan, Kementrian Kesehatan RI</td></tr>
        <tr><td style="border:none; vertical-align:top;">2.</td><td style="border:none; font-size:8pt;">Nilai Ketidakpastian pengukuran mempunyai tingkat kepercayaan 95% dengan factor cakupan k = 2</td></tr>
        <tr><td style="border:none; vertical-align:top;">3.</td><td style="border:none; font-size:8pt;">Hasil pengujian dan kalibrasi hanya terkait dengan nomor seri di atas</td></tr>
        <tr><td style="border:none; vertical-align:top;">4.</td><td style="border:none; font-size:8pt;">Nilai sebenarnya adalah nilai penunjukan alat ditambah dengan nilai koreksi</td></tr>
    </table>

    @include('pdf.partials.footer', ['pageNumber' => 3])
</div>

</body>
</html>
