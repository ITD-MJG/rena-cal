<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Kalibrasi - {{ data_get($worksheet->payload, 'device.serial') ?? $worksheet->device_serial }}</title>
    <style>
        * { margin: 0; padding: 0; }
        /* DejaVu Sans is bundled with dompdf and carries the Greek block, so the
           ohm sign in the electrical-safety unit survives. Arial/Helvetica fall
           back to the WinAnsi core fonts, which have no Ω and print a "?". */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; }
        /* 25mm top = 15mm blank for the pre-printed letterhead + 10mm original margin.
           The paper already carries the RENA header, so nothing is drawn there. */
        .page { width: 170mm; padding: 25mm 20mm 10mm 20mm; page-break-after: always; }
        .page:last-child { page-break-after: avoid; }

        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 4px 6px; vertical-align: top; }

        /* Top margin separates each numbered group from the table above it. */
        .section-title { font-weight: bold; font-size: 10pt; color: #003366; padding: 6px 0 3px 0; border-bottom: 1.5px solid #003366; margin: 12px 0 6px 0; }
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

        /* The footer is position:fixed, so dompdf repeats it on every page and the
           page number comes from the CSS page counter rather than a passed value. */
        .pdf-footer { position: fixed; bottom: 0; left: 20mm; right: 20mm; padding-bottom: 10mm; }
        .pdf-footer-page { text-align: right; font-size: 9pt; color: #000; }
        .pdf-footer-counter::after { content: counter(page); }
        .pdf-footer-rule { border-top: 1px solid #000; margin: 2mm 0; }
        .page-1 { font-size: 12pt; }
    </style>
</head>
<body>
@include('pdf.partials.footer')
@php
    $payload = $worksheet->payload ?? [];
    $conclusion = data_get($payload, 'conclusion');
    $isLaik = $conclusion === \App\Models\Worksheet::CONCLUSION_LAIK;
    // An unreadable verdict cell is not evidence of anything: show a placeholder
    // rather than declaring the equipment unfit.
    $verdict = $conclusion !== null ? strtoupper($conclusion) : '—';

    // Measurements arrive as raw floats (135.67030976400375). A certificate shows
    // three decimals with trailing zeros trimmed (135.67), never the float's
    // full 17-digit representation.
    $num = function ($value, int $decimals = 3) {
        if ($value === null || $value === '') {
            return '—';
        }
        if (! is_numeric($value)) {
            return $value;
        }
        if ((float) $value === floor((float) $value)) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
    };
@endphp

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 1: COVER --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page page-1">
    <div class="center" style="margin: 10px 0;">
        <div style="font-size: 13pt; font-weight: bold; text-decoration: underline;">SERTIFIKAT KALIBRASI</div>
    </div>

    <table style="margin-bottom: 10px;">
        <tr>
            <td style="border:none; width:50%; vertical-align:top;">
                <table>
                    <tr><td style="border:none; width:48%;">Nomor Sertifikat</td><td style="border:none;">: {{ $worksheet->cert_number ?? '—' }}</td></tr>
                    <tr><td style="border:none;">Nomor Pesanan</td><td style="border:none;">: {{ $worksheet->order_number ?? '—' }}</td></tr>
                </table>
            </td>
            <td style="border:none; width:50%;"></td>
        </tr>
    </table>

    <table style="margin-bottom: 10px;">
        <tr>
            <td style="border:none; width:50%; vertical-align:top;">
                <div class="bold" style="margin-bottom:4px;">IDENTITAS ALAT</div>
                <table>
                    <tr><td style="border:none; width:48%;">Nama Alat</td><td style="border:none;">: {{ data_get($payload, 'device.name') ?? '—' }}</td></tr>
                    <tr><td style="border:none;">Merk</td><td style="border:none;">: {{ data_get($payload, 'device.brand') ?? '—' }}</td></tr>
                    <tr><td style="border:none;">Tipe</td><td style="border:none;">: {{ data_get($payload, 'device.type') ?? '—' }}</td></tr>
                    <tr><td style="border:none;">Nomor Seri</td><td style="border:none;">: {{ data_get($payload, 'device.serial') ?? '—' }}</td></tr>
                </table>
            </td>
            <td style="border:none; width:50%; vertical-align:top;">
                <div class="bold" style="margin-bottom:4px;">IDENTITAS PEMILIK</div>
                <div style="margin-bottom:6px;">{{ data_get($payload, 'customer.name') ?? '—' }}</div>
                <div>{{ $worksheet->device?->customer?->address ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 10px;">
        <tr>
            <td style="border:none; width:50%;">Tanggal Penerimaan</td>
            <td style="border:none; font-weight:bold;">{{ strtoupper(data_get($payload, 'calibration.received_date') ?? '—') }}</td>
        </tr>
        <tr>
            <td style="border:none;">Tanggal Kalibrasi</td>
            <td style="border:none; font-weight:bold;">{{ strtoupper(data_get($payload, 'calibration.calibration_date') ?? '—') }}</td>
        </tr>
        <tr>
            <td style="border:none;">Kesimpulan</td>
            <td style="border:none; font-weight:bold; {{ $conclusion === null ? '' : ($isLaik ? 'color:#155724;' : 'color:#721c24;') }}">
                {{ $verdict }}
            </td>
        </tr>
    </table>

<table class="sig-table">
    <tr>
        <td style="width:60%;">
            <div style="margin-bottom: 5px;">Jakarta, {{ now()->format('d F Y') }}</div>
            <div class="bold">Penanggung Jawab</div>
            <div class="center" style="margin: 14px 0;">
                <div class="small" style="margin-bottom: 4px;">QR</div>
                <div style="border:1px dashed #999; width:80px; height:80px; margin: 0 auto;"></div>
            </div>
            <div class="small" style="margin-top: 14px;">Dokumen ini ditandatangani secara elektronik</div>
        </td>
        <td style="width:40%;"></td>
    </tr>
</table>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 2: SECTIONS 1-6 --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    {{-- 1. Identitas Alat --}}
    <div class="section-title">1. IDENTITAS ALAT</div>
    <table class="data-table">
        <tr>
            <th>Merk</th><th>Tipe</th><th>Nomor Seri</th>
        </tr>
        <tr>
            <td>{{ data_get($payload, 'device.brand') ?? '—' }}</td>
            <td>{{ data_get($payload, 'device.type') ?? '—' }}</td>
            <td>{{ data_get($payload, 'device.serial') ?? '—' }}</td>
        </tr>
    </table>

    {{-- 2. Pelaksanaan Kalibrasi --}}
    <div class="section-title">2. PELAKSANAAN KALIBRASI</div>
    <table class="data-table">
        <tr><th class="text-left" style="width:35%;">Nama Pelayanan</th><td class="text-left" colspan="3">{{ data_get($payload, 'service.name') ?? '—' }}</td></tr>
        <tr><th class="text-left">Ruangan Kalibrasi</th><td class="text-left" colspan="3">{{ data_get($payload, 'calibration.room') ?? '—' }}</td></tr>
        <tr><th class="text-left">Pelaksana Teknis</th><td class="text-left" colspan="3">{{ data_get($payload, 'calibration.technician') ?? '—' }}</td></tr>
    </table>

    {{-- 3. Kondisi Lingkungan --}}
    <div class="section-title">3. KONDISI LINGKUNGAN</div>
    <table class="data-table">
        <tr><th>Parameter</th><th>Nilai</th><th>Ketidakpastian</th></tr>
        <tr>
            <td class="text-left">Suhu</td>
            <td>{{ $num(data_get($payload, 'environment.temperature.value')) }} °C</td>
            <td>± {{ $num(data_get($payload, 'environment.temperature.uncert')) }} °C</td>
        </tr>
        <tr>
            <td class="text-left">Kelembapan</td>
            <td>{{ $num(data_get($payload, 'environment.humidity.value')) }} % RH</td>
            <td>± {{ $num(data_get($payload, 'environment.humidity.uncert')) }} % RH</td>
        </tr>
        <tr>
            <td class="text-left">Tegangan Utama</td>
            <td colspan="2">{{ $num(data_get($payload, 'environment.main_voltage')) }} Vac</td>
        </tr>
    </table>

    {{-- 4. Daftar Alat --}}
    <div class="section-title">4. DAFTAR ALAT YANG DIGUNAKAN</div>
    <table class="data-table">
        <tr><th>No.</th><th>Nama Alat</th><th>Merk</th><th>Tipe</th><th>No. Seri</th><th>Ketertelusuran</th></tr>
        @forelse (data_get($payload, 'instruments', []) as $i => $instrument)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="text-left">{{ data_get($instrument, 'name') ?? '—' }}</td>
                <td>{{ data_get($instrument, 'brand') ?? '—' }}</td>
                <td>{{ data_get($instrument, 'type') ?? '—' }}</td>
                <td>{{ data_get($instrument, 'serial') ?? '—' }}</td>
                <td>{{ data_get($instrument, 'traceability') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6">—</td></tr>
        @endforelse
    </table>

    {{-- 5. Pemeriksaan Fisik --}}
    <div class="section-title">5. PEMERIKSAAN KONDISI FISIK DAN FUNGSI</div>
    <table class="data-table">
        <tr><th>No.</th><th>Parameter</th><th>Hasil</th></tr>
        @forelse (data_get($payload, 'physical', []) as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="text-left">{{ data_get($row, 'parameter') ?? '—' }}</td>
                <td>{{ data_get($row, 'result') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="3">—</td></tr>
        @endforelse
    </table>

    {{-- 6. Pengukuran Keselamatan Listrik --}}
    <div class="section-title">6. PENGUKURAN KESELAMATAN LISTRIK</div>
    <table class="data-table">
        <tr><th>No.</th><th>Parameter</th><th>Terukur</th><th>Ambang Batas</th></tr>
        @forelse (data_get($payload, 'electrical', []) as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="text-left">{{ data_get($row, 'parameter') ?? '—' }}</td>
                <td>{{ $num(data_get($row, 'measured')) }} {{ data_get($row, 'unit') ?? '' }}</td>
                <td>{{ $num(data_get($row, 'limit')) }} {{ data_get($row, 'limit_unit') ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">—</td></tr>
        @endforelse
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE 3: SECTIONS 7-10 --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    {{-- 7. Hasil Kalibrasi --}}
    <div class="section-title">7. HASIL KALIBRASI</div>

    <div class="bold" style="margin: 4px 0;">1. Akurasi Temperature</div>
    <table class="data-table">
        <tr><th>Setting Alat (°C)</th><th>Penunjukan Standar (°C)</th><th>Koreksi (°C)</th><th>Ambang Batas</th></tr>
        @forelse (data_get($payload, 'performance.temperature', []) as $row)
            <tr>
                <td>{{ $num(data_get($row, 'setting')) }}</td>
                <td>{{ $num(data_get($row, 'standard')) }}</td>
                <td>{{ $num(data_get($row, 'correction')) }}</td>
                <td>{{ data_get($row, 'limit') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">—</td></tr>
        @endforelse
    </table>
    @foreach (data_get($payload, 'performance.temperature_uncertainty', []) as $row)
        <div style="font-size:8pt; margin: 3px 0;">
            Ketidakpastian Pengukuran : ± {{ $num(data_get($row, 'value')) }} {{ data_get($row, 'unit') ?? '' }}
        </div>
    @endforeach

    <div class="bold" style="margin: 14px 0 4px 0;">2. Akurasi Waktu</div>
    <table class="data-table">
        <tr><th>Setting Alat (Menit)</th><th>Penunjukan Standar (Menit)</th><th>Koreksi (Menit)</th><th>Ambang Batas</th></tr>
        @forelse (data_get($payload, 'performance.time', []) as $row)
            <tr>
                <td>{{ $num(data_get($row, 'setting')) }}</td>
                <td>{{ $num(data_get($row, 'standard')) }}</td>
                <td>{{ $num(data_get($row, 'correction')) }}</td>
                <td>{{ data_get($row, 'limit') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">—</td></tr>
        @endforelse
    </table>
    @foreach (data_get($payload, 'performance.time_uncertainty', []) as $row)
        <div style="font-size:8pt; margin: 3px 0;">
            Ketidakpastian Pengukuran : ± {{ $num(data_get($row, 'value')) }} {{ data_get($row, 'unit') ?? '' }}
        </div>
    @endforeach

    {{-- 8. Kesimpulan --}}
    <div class="section-title">8. KESIMPULAN</div>
    <div class="center" style="margin: 8px 0;">
        <span class="badge {{ $conclusion === null ? '' : ($isLaik ? 'badge-laik' : 'badge-tidak') }}">
            {{ $verdict }}
        </span>
    </div>

    {{-- 9. Keterangan --}}
    <div class="section-title">9. KETERANGAN</div>
    <table>
        @forelse (data_get($payload, 'notes', []) as $i => $note)
            <tr>
                <td style="border:none; width:5%; vertical-align:top;">{{ $i + 1 }}.</td>
                <td style="border:none; font-size:8pt;">{{ data_get($note, 'text') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td style="border:none;">—</td></tr>
        @endforelse
    </table>
</div>
</body>
</html>
