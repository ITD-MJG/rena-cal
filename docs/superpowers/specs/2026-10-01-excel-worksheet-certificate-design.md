# Excel Worksheet → PDF Certificate

**Date:** 2026-10-01
**Status:** Design approved, awaiting implementation plan

## Purpose

Replace the deleted DB-driven calibration worksheet subsystem with an Excel-first one:
upload a filled calibration workbook, read the two sheets the certificate needs, persist
the parsed result, and render a 3-page calibration certificate PDF from that stored
payload.

The uploaded workbook is the single source of truth for all technical data. Nothing is
recomputed — the workbook's own cached formula results are read verbatim.

## Background

The working tree contains ~30 uncommitted deletions of a previous implementation
(`app/Calibration/*`, 7 models, 8 migrations, a Filament resource, and a certificate
Blade view). That design stored a worksheet across 8 normalised tables and recomputed
scores through `WorksheetCalculator` / `UncertaintyEngine`. It is being abandoned:
the workbook already contains every computed value, so the calculation engine and the
8-table schema are redundant.

All deleted files remain intact at `HEAD` (`a344d4c`).

## Non-Goals

- No calculation engine, no uncertainty budgeting, no score recomputation.
- No multi-device workbooks. One uploaded workbook produces exactly one worksheet.
- No per-device-type templates. All calibration workbooks share one identical layout.
- No visual cell-mapping UI.
- No reading of `PENGOLAHAN DATA`, `KETIDAKPASTIAN`, `SERT. DATA LOGGER`,
  `SERTIFIKAT ESA`, `SERTIFIKAT THERMOHYGROMETER`, or `SERTIFIKAT STOPWATCH`.

## Decisions

| Decision | Choice |
|---|---|
| Old design | Delete it (commit the pending deletions) |
| Cardinality | 1 workbook → 1 worksheet → 1 PDF |
| Sheet mapping | Cell map as `private const` in one parser class |
| Sheets read | `LAPORAN HASIL` + `LEMBAR KERJA` |
| Templates | None — single identical layout for all files |
| Parsed data | Persisted as JSON on the worksheet record |
| Page-1 customer name | `LEMBAR KERJA!F7` |
| Page-1 customer address | `Device->customer->address` |
| Reader library | Direct PhpSpreadsheet, not maatwebsite/excel |

### Why not maatwebsite/excel

Measured against the real workbook:

1. `WithCalculatedFormulas` on a single sheet returns `#REF!` for every value needed.
   `LAPORAN HASIL` is built entirely from cross-sheet references
   (`'LEMBAR KERJA'!F8`, `'PENGOLAHAN DATA'!U23`); a single-sheet import never loads
   the referenced sheets, so calculation fails.
2. Plain `ToCollection` over all sheets exhausts 512 MB and 1 GB memory limits.
   `readDataOnly(true)` + `setLoadSheetsOnly()` handles the same file at 20 MB peak
   in 0.02 s.
3. `ToCollection` rows are numerically indexed, turning a cell map into column offsets
   that break on any column insert.

The values are already cached in the file and readable via
`getOldCalculatedValue()`, which requires loading the referenced sheets. That is what
the parser does.

### Why no templates

Every calibration workbook uses the same layout. A `WorksheetTemplate` contract,
a registry, and a `template_key` column would be indirection with exactly one
implementation. The cell map lives as a `private const` in the parser. If a second
layout ever appears, extracting an interface at that point is cheap.

## Architecture

```
app/Worksheets/
  WorksheetParser.php   parse(string $path): array
  CellReader.php        resolve a cell: oldCalc → calculated → literal
```

No contract, no registry, no `Templates/` directory.

### Reading strategy

Load the workbook once, restricted to the sheets actually needed:

```php
$reader = IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(true);
$reader->setLoadSheetsOnly(['LAPORAN HASIL', 'LEMBAR KERJA']);
$spreadsheet = $reader->load($path);
```

`readDataOnly(true)` is required for the measured memory profile and is safe because
no styling is read.

**Do not iterate cells.** `getRowIterator()` / `getCellIterator()` exhausts 2 GB on the
heavier sheets of this workbook. Read only the coordinates named in the cell map.

### Cell resolution

`CellReader` resolves each coordinate through a three-step fallback, in order:

1. `getOldCalculatedValue()` — the cached result stored in the file. This is the
   normal path; it returns `LOKAL` for `LAPORAN HASIL!G2`, whose raw value is
   `='LEMBAR KERJA'!F8`.
2. `getCalculatedValue()` — recompute, for a file saved without cached values.
3. `getValue()` — the literal, for plain non-formula cells.

Returns `null` when the cell is empty or the coordinate is malformed. Never throws on
a missing cell.

### Cell map

```php
private const SCALARS = [
    // LAPORAN HASIL — single-value cells
    'device.brand'       => ['LAPORAN HASIL', 'G2'],
    'device.type'        => ['LAPORAN HASIL', 'G3'],
    'device.serial'      => ['LAPORAN HASIL', 'G4'],
    'service.name'       => ['LAPORAN HASIL', 'G7'],
    'calibration_room'   => ['LAPORAN HASIL', 'G8'],
    'technician_name'    => ['LAPORAN HASIL', 'G9'],
    'temperature.value'  => ['LAPORAN HASIL', 'H12'],
    'temperature.uncert' => ['LAPORAN HASIL', 'J12'],
    'humidity.value'     => ['LAPORAN HASIL', 'H13'],
    'humidity.uncert'    => ['LAPORAN HASIL', 'J13'],
    'main_voltage'       => ['LAPORAN HASIL', 'H14'],
    'conclusion'         => ['LAPORAN HASIL', 'B52'],

    // LEMBAR KERJA — page-1 identity, absent from LAPORAN HASIL
    'device.name'        => ['LEMBAR KERJA', 'I3'],
    'customer.name'      => ['LEMBAR KERJA', 'F7'],
    'received_date'      => ['LEMBAR KERJA', 'S9'],
    'calibration_date'   => ['LEMBAR KERJA', 'S10'],
];
```

Repeating row blocks are declared separately. Ranges are inclusive and skip rows whose
leading cell is empty:

```php
private const ROWS = [
    'instruments' => ['LAPORAN HASIL', 18, 20,
        ['name' => 'C', 'brand' => 'I', 'type' => 'M',
         'serial' => 'Q', 'traceability' => 'U']],
    'physical'    => ['LAPORAN HASIL', 24, 29,
        ['parameter' => 'C', 'result' => 'J']],
    'electrical'  => ['LAPORAN HASIL', 33, 36,
        ['parameter' => 'C', 'measured' => 'O', 'limit' => 'U']],
    'performance' => ['LAPORAN HASIL', 40, 49,
        ['label' => 'B', 'setting' => 'I', 'standard' => 'J',
         'correction' => 'O', 'limit' => 'P']],
    'notes'       => ['LAPORAN HASIL', 55, 58, ['text' => 'C']],
];
```

### Normalisation

Two private methods:

- `trimValues()` — trim strings, collapse internal whitespace runs, cast numeric
  strings to numbers.
- `conclusion()` — map the workbook's checkbox glyph to a stored enum. The workbook
  writes `~` next to the selected option; `Laik Pakai` and `Tidak Laik Pakai` are the
  two stored values.

`customer.name` from `LEMBAR KERJA!F7` contains a double space
(`Klinik Pratama Ocean Dental  Radio Dalam`) and is collapsed by `trimValues()`.

### Parser output

```php
[
    'device'     => ['name' => …, 'brand' => …, 'type' => …, 'serial' => …],
    'customer'   => ['name' => …],
    'service'    => ['name' => …],
    'calibration'=> ['room' => …, 'technician' => …, 'received_date' => …,
                     'calibration_date' => …],
    'environment'=> ['temperature' => ['value' => …, 'uncert' => …],
                     'humidity'    => ['value' => …, 'uncert' => …],
                     'main_voltage' => …],
    'instruments'=> [ … ],
    'physical'   => [ … ],
    'electrical' => [ … ],
    'performance'=> [ … ],
    'notes'      => [ … ],
    'conclusion' => 'Laik Pakai' | 'Tidak Laik Pakai' | null,
]
```

## Persistence

One table, `worksheets`.

```
id
device_id                 nullable FK → devices, nullOnDelete
source_workbook_path      string
original_filename         string
payload                   json
cert_number               string  nullable
order_number              string  nullable
status                    enum: draft | generated   default draft
certificate_pdf_path      string  nullable
device_serial             string  nullable   (denormalised for the table view)
customer_name             string  nullable   (denormalised for the table view)
calibration_date          date    nullable   (denormalised for the table view)
conclusion                enum    nullable   (denormalised for the table view)
timestamps

index (device_id, calibration_date)
index (status)
```

`cert_number` and `order_number` are not present in the workbook and are typed on the
form. The four denormalised columns exist so the resource table can sort and search
without unpacking `payload`.

## User Interface

Filament v5 resource, navigation group `Kalibrasi`, model label `Lembar Kerja`.

**Form** (`Schemas/WorksheetForm`)

- `FileUpload` — xlsx only, disk `public`, directory `worksheets`.
- `Select` — device, searchable, preloaded. Auto-selected after parse by matching
  `Device.serial_number` against the parsed `device.serial`.
- `TextInput` — `cert_number`, `order_number`.

**Table** (`Tables/WorksheetsTable`)

- `device_serial`, `customer_name`, `calibration_date`, `conclusion` (badge:
  success / danger), `status`, `created_at`. Default sort `created_at` desc.
- Record actions: view, delete.

**View** (`Pages/ViewWorksheet`)

- Infolist with one `Section` per parsed section, mirroring the certificate.
- Header actions: `Parse Ulang` (re-read the stored workbook, overwrite `payload`,
  requires confirmation) and `Generate Sertifikat` (streamed PDF download).
- Pages: index, create, view, edit.

**Create flow**

1. Upload + choose device + enter numbers.
2. On save: move the file to disk, parse it, persist `payload` and the denormalised
   columns, set `status = draft`.
3. Redirect to view.

A parse failure surfaces a persistent danger notification naming the sheet and
coordinate that failed. The record is still saved, with an empty `payload`, so the
upload is not lost and `Parse Ulang` can be retried.

## PDF

`resources/views/pdf/certificate.blade.php`, rendered by
`barryvdh/laravel-dompdf` at A4 portrait, driven entirely by the stored `payload`.

Three pages, matching the reference `AUTOCLAVE_A250705-510.pdf`:

- **Page 1** — certificate number, order number, device identity, customer identity
  (name from the workbook, address from `Device->customer->address`), dates,
  conclusion, signature block, QR placeholder.
- **Page 2** — sections 1 Identitas Alat, 2 Pelaksanaan Kalibrasi,
  3 Kondisi Lingkungan, 4 Daftar Alat, 5 Pemeriksaan Fisik, 6 Pengukuran Keselamatan
  Listrik.
- **Page 3** — sections 7 Hasil Kalibrasi, 8 Kesimpulan, 9 Keterangan, 10 footer notes.

Reuses the existing `pdf.partials.header` and `pdf.partials.footer`.

The mangled path `resources/views/pdf/partials/footer.blade.php\{"content` is removed
as part of the cleanup.

## Cleanup

1. Commit the pending deletions as one commit.
2. Remove the mangled footer path.
3. Restore `Autoclave.xlsx` from `HEAD` to `tests/Fixtures/autoclave.xlsx`.
4. Keep `AUTOCLAVE_A250705-510.pdf` at `HEAD` only, as the visual reference; do not
   restore it to the working tree.

## Testing

**`WorksheetParserTest`** (unit, fixture `tests/Fixtures/autoclave.xlsx`)

Asserts exact extracted values, which were verified against the real file:

| Assertion | Expected |
|---|---|
| `device.brand` | `LOKAL` |
| `device.type` | `YA28X6T/8` |
| `device.serial` | `A250705-510` |
| `device.name` | `AUTOCLAVE` |
| `customer.name` | `Klinik Pratama Ocean Dental Radio Dalam` (double space collapsed) |
| `service.name` | `Instalasi sterilisasi pusat` |
| `calibration_room` | `STERILISASI` |
| `technician_name` | `MARSHEL ASYRAF DALTAFIKA` |
| `temperature.value` | `24.55` |
| `temperature.uncert` | `0.63` |
| `humidity.value` | `55.5` |
| `humidity.uncert` | `2.9` |
| `main_voltage` | `222` |
| `conclusion` | `Tidak Laik Pakai` |
| `instruments` | 3 rows, first `Electrical Safety Analyzer` / `RIGEL` / `288 PLUS` / `17Q-1323` / `LK-032-IDN` |
| `physical` | 6 rows, all `Baik` |
| `electrical` | 4 rows, first measured `0.08529005530253725` |
| `performance` | rows including standard `135.67030976400375`, correction `1.670309764003747` |
| `notes` | 4 rows |

**`CellReaderTest`** (unit)

- Cached value preferred over recompute.
- Falls back to recompute when no cached value exists.
- Falls back to the literal for a non-formula cell.
- Empty and malformed coordinates return `null` without throwing.

**`WorksheetResourceTest`** (feature)

- Uploading the fixture creates a worksheet with a populated `payload` and
  `status = draft`.
- Device auto-matches by serial.
- `Generate Sertifikat` returns a PDF response with 3 pages.
- A workbook missing a required sheet saves with an empty payload and reports failure.

Run with `php artisan test --compact` filtered to the affected files.

## Risks

- **Workbook layout drift.** The cell map is coordinate-bound. Any column or row insert
  in a future workbook silently shifts values. Mitigated by the parser test asserting
  exact values against a committed fixture; a shifted layout fails loudly.
- **Missing cached values.** A workbook saved by a tool that omits cached results
  forces the recompute path, which needs every referenced sheet loaded. The parser
  loads `LAPORAN HASIL` and `LEMBAR KERJA`; a formula reaching into
  `PENGOLAHAN DATA` will fall through to `null` on that path. The cached-value path
  covers the real files.
- **Memory.** Cell iteration must be avoided entirely; only mapped coordinates are read.
