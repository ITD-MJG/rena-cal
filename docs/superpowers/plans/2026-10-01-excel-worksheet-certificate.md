# Excel Worksheet → PDF Certificate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upload a filled calibration workbook, read `LAPORAN HASIL` + `LEMBAR KERJA`, persist the parsed payload, and render a 3-page calibration certificate PDF from it.

**Architecture:** A `WorksheetParser` reads only the mapped coordinates of two sheets via direct PhpSpreadsheet with `readDataOnly(true)` + `setLoadSheetsOnly(...)`, resolving each cell through cached-value → recompute → literal. The result is stored as JSON on a single `worksheets` table. A Filament v5 resource owns upload and display; a Blade view renders the certificate through dompdf.

**Tech Stack:** PHP 8.4, Laravel 13, Filament v5, PhpSpreadsheet (via `maatwebsite/excel`'s dependency), `barryvdh/laravel-dompdf` ^3.1, Pest 4.

**Spec:** `docs/superpowers/specs/2026-10-01-excel-worksheet-certificate-design.md`

## Global Constraints

- Read **only** `LAPORAN HASIL` and `LEMBAR KERJA`. Never read the other six sheets.
- **Never iterate cells.** `getRowIterator()` / `getCellIterator()` exhausts 2 GB on this workbook. Read only named coordinates.
- Load the workbook as `setReadDataOnly(true)` + `setLoadSheetsOnly(['LAPORAN HASIL', 'LEMBAR KERJA'])`. Measured 20 MB peak / 0.02 s.
- Cell resolution order: `getOldCalculatedValue()` → `getCalculatedValue()` → `getValue()`. Return `null` when all are empty. Never throw on a missing cell.
- One workbook = one worksheet = one PDF. No multi-device parsing.
- No per-device templates. One fixed layout; the cell map is a `private const` in the parser.
- `conclusion` is stored as exactly `Laik Pakai` or `Tidak Laik Pakai`, or `null`.
- Certificate renders at A4 portrait via `Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])`.
- Run `vendor/bin/pint --dirty --format agent` before every commit that touches PHP.
- Run tests with `php artisan test --compact` filtered to the affected file.

## Review Focus

Inputs and conditions the spec implies but does not enumerate, most likely to bite first:

1. **A workbook saved without cached formula values.** Every mapped cell in `LAPORAN HASIL` is a cross-sheet formula; without the cache, resolution must fall back to recompute, which cannot reach `PENGOLAHAN DATA` (not loaded). Expect `null`, not a crash or a stale `#REF!`.
2. **A shifted workbook layout.** A column or row inserted upstream moves values under the fixed coordinates. Expect the parser test to fail loudly on exact values, never silently produce a plausible wrong certificate.
3. **An empty or absent mapped cell.** Expect `null` in the payload and a certificate that renders with a placeholder, not an exception.
4. **A cell holding a formula error string.** `#REF!` / `#VALUE!` must not be stored as a value and must not render as certificate data.
5. **Whitespace in workbook strings.** `LEMBAR KERJA!F7` contains a double space; `I18` contains a trailing space. Expect trimming and internal-run collapse.

---

### Task 1: Clean up the abandoned design

**Files:**
- Delete: the 30 pending working-tree deletions (already staged as deleted)
- Delete: `resources/views/pdf/partials/footer.blade.php\{"content` (mangled path)
- Create: `tests/Fixtures/autoclave.xlsx` (restored from `HEAD`)
- Delete: `tests/Feature/Calibration/CertificatePdfTest.php`, `tests/Feature/Calibration/WorksheetCalculationTest.php`, `tests/Feature/Filament/CalibrationWorksheetResourceTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a clean tree with no references to `App\Calibration\*` or `App\Models\CalibrationWorksheet`, and a committed test fixture at `tests/Fixtures/autoclave.xlsx`.

- [ ] **Step 1: Confirm the pending deletions are what we expect**

Run: `git status --short`
Expected: ~30 ` D ` entries for `app/Calibration/*`, `app/Models/Calibration*.php`, `database/migrations/2026_08_10_*.php`, the `CalibrationWorksheets` resource, `resources/views/pdf/certificate.blade.php`, plus the root `Autoclave.xlsx` and `AUTOCLAVE_A250705-510.pdf`.

- [ ] **Step 2: Restore the workbook as a test fixture**

```bash
mkdir -p tests/Fixtures
git show HEAD:Autoclave.xlsx > tests/Fixtures/autoclave.xlsx
```

- [ ] **Step 3: Remove the mangled footer path and the stale tests**

```bash
rm -f 'resources/views/pdf/partials/footer.blade.php\{"content'
rm -f tests/Feature/Calibration/CertificatePdfTest.php
rm -f tests/Feature/Calibration/WorksheetCalculationTest.php
rm -f tests/Feature/Filament/CalibrationWorksheetResourceTest.php
rmdir tests/Feature/Calibration 2>/dev/null || true
```

- [ ] **Step 4: Verify nothing still references the old namespace**

Run: `grep -rn "App\\\\Calibration\|CalibrationWorksheet" app/ tests/ database/ resources/`
Expected: no output.

- [ ] **Step 5: Run the full suite to confirm a green baseline**

Run: `php artisan test --compact`
Expected: PASS. The deleted tests are gone; no remaining test references the old design.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(calibration): remove DB-driven worksheet design

The workbook already carries every computed value, so the calculation engine,
the 8 normalised tables, and the per-device templates are redundant. Keep the
reference workbook as a test fixture for the Excel-first replacement."
```

---

### Task 2: CellReader

**Files:**
- Create: `app/Worksheets/CellReader.php`
- Test: `tests/Unit/CellReaderTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `CellReader::read(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $coordinate): string|int|float|null`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Worksheets\CellReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function sheetWith(array $cells): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    foreach ($cells as $coordinate => $value) {
        $sheet->setCellValue($coordinate, $value);
    }

    return $sheet;
}

it('returns the literal value of a plain cell', function () {
    $sheet = sheetWith(['A1' => 'AUTOCLAVE']);

    expect(CellReader::read($sheet, 'A1'))->toBe('AUTOCLAVE');
});

it('prefers the cached calculated value over recomputing', function () {
    $sheet = sheetWith(['A1' => '=1+1']);
    // No cached value exists on a freshly built spreadsheet, so this exercises
    // the recompute path and proves the fallback chain reaches it.
    expect(CellReader::read($sheet, 'A1'))->toBe(2);
});

it('returns the cached value when the file carries one', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', '=\'OTHER\'!B2');
    // Simulate the cache a real workbook stores alongside the formula.
    $sheet->getCell('A1')->setCalculatedValue('CACHED');

    expect(CellReader::read($sheet, 'A1'))->toBe('CACHED');
});

it('returns null for an empty cell', function () {
    $sheet = sheetWith([]);

    expect(CellReader::read($sheet, 'Z99'))->toBeNull();
});

it('returns null for a malformed coordinate without throwing', function () {
    $sheet = sheetWith(['A1' => 'x']);

    expect(CellReader::read($sheet, 'not-a-coordinate'))->toBeNull();
});

it('returns null for a formula error string', function () {
    $sheet = sheetWith(['A1' => '=#REF!']);

    expect(CellReader::read($sheet, 'A1'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CellReaderTest`
Expected: FAIL — `Class "App\Worksheets\CellReader" not found`.

- [ ] **Step 3: Implement `CellReader`**

`read()` must try, in order: `getOldCalculatedValue()`, then `getCalculatedValue()` inside a `try`/`catch`, then `getValue()`. Treat `null` and `''` as empty at each stage. Reject a coordinate that does not match `/^[A-Z]{1,3}[0-9]{1,7}$/`. If the final value is a string matching `/^#(REF|VALUE|N\/A|DIV\/0|NAME|NULL|NUM)!$/`, return `null`. Otherwise return the value.

The `try`/`catch` around `getCalculatedValue()` is required: recompute throws when a referenced sheet is not loaded, which is the expected case for a cache-less workbook.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CellReaderTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Worksheets/CellReader.php tests/Unit/CellReaderTest.php
git commit -m "feat(worksheets): add CellReader with cached-value fallback chain"
```

---

### Task 3: WorksheetParser

**Files:**
- Create: `app/Worksheets/WorksheetParser.php`
- Test: `tests/Unit/WorksheetParserTest.php`

**Interfaces:**
- Consumes: `CellReader::read(Worksheet $sheet, string $coordinate): string|int|float|null`
- Produces:
  - `WorksheetParser::parse(string $path): array`
  - `WorksheetParser::SHEETS` — `public const array` of the two sheet names
  - The payload shape documented in the spec's "Parser output" section.

The cell map, verified against `tests/Fixtures/autoclave.xlsx`:

```php
private const CONCLUSION = ['LAPORAN HASIL', 'B52'];

private const SCALARS = [
    'LAPORAN HASIL' => [
        'device.brand'       => 'G2',   // LOKAL
        'device.type'        => 'G3',   // YA28X6T/8
        'device.serial'      => 'G4',   // A250705-510
        'service.name'       => 'G7',   // Instalasi sterilisasi pusat
        'calibration.room'   => 'G8',   // STERILISASI
        'calibration.technician' => 'G9', // MARSHEL ASYRAF DALTAFIKA
        'environment.temperature.value'  => 'H12', // 24.55
        'environment.temperature.uncert' => 'J12', // 0.63
        'environment.humidity.value'     => 'H13', // 55.5
        'environment.humidity.uncert'    => 'J13', // 2.9
        'environment.main_voltage'       => 'J14', // 222
    ],
    'LEMBAR KERJA' => [
        'device.name'         => 'I3',  // AUTOCLAVE
        'customer.name'       => 'F7',  // Klinik Pratama Ocean Dental  Radio Dalam
        'calibration.received_date'    => 'S9',  // 02 JUL 2026
        'calibration.calibration_date' => 'S10', // 02 JUL 2026
    ],
];

private const ROWS = [
    'instruments' => ['LAPORAN HASIL', 18, 20,
        ['name' => 'C', 'brand' => 'I', 'type' => 'M', 'serial' => 'Q', 'traceability' => 'U']],
    'physical'    => ['LAPORAN HASIL', 24, 29,
        ['parameter' => 'C', 'result' => 'J']],
    'electrical'  => ['LAPORAN HASIL', 33, 36,
        ['parameter' => 'C', 'measured' => 'O', 'unit' => 'R', 'limit' => 'U', 'limit_unit' => 'X']],
    'performance.temperature' => ['LAPORAN HASIL', 42, 42,
        ['setting' => 'B', 'standard' => 'I', 'correction' => 'O', 'limit' => 'T']],
    'performance.temperature_uncertainty' => ['LAPORAN HASIL', 43, 43,
        ['value' => 'O', 'unit' => 'P']],
    'performance.time' => ['LAPORAN HASIL', 48, 48,
        ['setting' => 'B', 'standard' => 'I', 'correction' => 'O', 'limit' => 'T']],
    'performance.time_uncertainty' => ['LAPORAN HASIL', 49, 49,
        ['value' => 'O', 'unit' => 'P']],
    'notes'       => ['LAPORAN HASIL', 55, 58, ['text' => 'C']],
];
```

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Worksheets\WorksheetParser;

function parsed(): array
{
    return (new WorksheetParser)->parse(__DIR__.'/../Fixtures/autoclave.xlsx');
}

it('reads the device identity from LAPORAN HASIL', function () {
    $p = parsed();

    expect($p['device']['brand'])->toBe('LOKAL')
        ->and($p['device']['type'])->toBe('YA28X6T/8')
        ->and($p['device']['serial'])->toBe('A250705-510')
        ->and($p['device']['name'])->toBe('AUTOCLAVE');
});

it('collapses whitespace in workbook strings', function () {
    // LEMBAR KERJA!F7 holds "Klinik Pratama Ocean Dental  Radio Dalam".
    expect(parsed()['customer']['name'])
        ->toBe('Klinik Pratama Ocean Dental Radio Dalam');
});

it('reads the calibration administration block', function () {
    $p = parsed();

    expect($p['service']['name'])->toBe('Instalasi sterilisasi pusat')
        ->and($p['calibration']['room'])->toBe('STERILISASI')
        ->and($p['calibration']['technician'])->toBe('MARSHEL ASYRAF DALTAFIKA')
        ->and($p['calibration']['received_date'])->toBe('02 JUL 2026')
        ->and($p['calibration']['calibration_date'])->toBe('02 JUL 2026');
});

it('reads the environment block', function () {
    $p = parsed();

    expect($p['environment']['temperature']['value'])->toEqual(24.55)
        ->and($p['environment']['temperature']['uncert'])->toEqual(0.63)
        ->and($p['environment']['humidity']['value'])->toEqual(55.5)
        ->and($p['environment']['humidity']['uncert'])->toEqual(2.9)
        ->and($p['environment']['main_voltage'])->toEqual(222);
});

it('reads the instrument list', function () {
    $p = parsed();

    expect($p['instruments'])->toHaveCount(3)
        ->and($p['instruments'][0]['name'])->toBe('Electrical Safety Analyzer')
        ->and($p['instruments'][0]['brand'])->toBe('RIGEL')
        ->and($p['instruments'][0]['type'])->toBe('288 PLUS')
        ->and($p['instruments'][0]['serial'])->toBe('17Q-1323')
        ->and($p['instruments'][0]['traceability'])->toBe('LK-032-IDN');
});

it('reads the physical inspection block', function () {
    $p = parsed();

    expect($p['physical'])->toHaveCount(6)
        ->and($p['physical'][0]['parameter'])->toBe('Badan dan permukaan')
        ->and($p['physical'][0]['result'])->toBe('Baik');
});

it('reads the electrical safety block', function () {
    $p = parsed();

    expect($p['electrical'])->toHaveCount(4)
        ->and($p['electrical'][0]['parameter'])->toContain('Resistansi pembumian')
        ->and($p['electrical'][0]['measured'])->toEqual(0.08529005530253725)
        ->and($p['electrical'][0]['unit'])->toBe('Ω')
        ->and($p['electrical'][0]['limit'])->toEqual(0.3);
});

it('reads the calibration results block', function () {
    $p = parsed();

    expect($p['performance']['temperature'][0]['setting'])->toEqual(134)
        ->and($p['performance']['temperature'][0]['standard'])->toEqual(135.67030976400375)
        ->and($p['performance']['temperature'][0]['correction'])->toEqual(1.670309764003747)
        ->and($p['performance']['temperature_uncertainty'][0]['value'])->toEqual(0.06855226743147864)
        ->and($p['performance']['time'][0]['setting'])->toEqual(3)
        ->and($p['performance']['time'][0]['correction'])->toEqual(0);
});

it('normalises the conclusion to a stored value', function () {
    // LAPORAN HASIL!B52 reads "DINYATAKAN TIDAK LAIK PAKAI".
    expect(parsed()['conclusion'])->toBe('Tidak Laik Pakai');
});

it('reads the keterangan notes', function () {
    $p = parsed();

    expect($p['notes'])->toHaveCount(4)
        ->and($p['notes'][0]['text'])->toContain('Instruksi Kerja (RKS/MT/IK.01-010)');
});

it('returns an empty payload when a required sheet is missing', function () {
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('SOMETHING ELSE');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    expect((new WorksheetParser)->parse($path))->toBe([]);

    unlink($path);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WorksheetParserTest`
Expected: FAIL — `Class "App\Worksheets\WorksheetParser" not found`.

- [ ] **Step 3: Implement `WorksheetParser::parse(string $path): array`**

Implementation notes, in order:

1. Guard: if `$path` is not readable, return `[]`.
2. Load via `IOFactory::createReader('Xlsx')` with `setReadDataOnly(true)` and `setLoadSheetsOnly(self::SHEETS)`.
3. If either sheet in `self::SHEETS` is absent from the loaded spreadsheet, return `[]`.
4. Walk `self::SCALARS`, calling `CellReader::read()`. Build a nested array by splitting each key on `.` and nesting.
5. Walk `self::ROWS`. For each block, loop `$start..$end` inclusive, read the declared columns, and **skip the row when the first declared column is empty**. Append surviving rows in sheet order.
6. `trimValues()`: recursively trim strings, collapse internal whitespace runs (`preg_replace('/\s+/', ' ', $v)`), and cast a numeric string to `int`/`float` — but only when the original value is not a date-like string (`02 JUL 2026` must stay a string) and not one of the unit strings.
7. `conclusion()`: take the raw `B52` string, strip a leading `DINYATAKAN `, and uppercase-compare. Return `Laik Pakai`, `Tidak Laik Pakai`, or `null`. Anything else is `null`.

The conclusion lives at a dotted key (`conclusion`) alongside nested ones; assign it to the top level of the payload.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=WorksheetParserTest`
Expected: PASS, 11 tests.

If any assertion fails on a numeric type, inspect the actual value with `var_dump` before adjusting the expectation — the fixture is the source of truth, not this plan.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Worksheets/WorksheetParser.php tests/Unit/WorksheetParserTest.php
git commit -m "feat(worksheets): parse LAPORAN HASIL and LEMBAR KERJA into a payload"
```

---

### Task 4: Worksheet model and migration

**Files:**
- Create: `database/migrations/2026_10_01_000001_create_worksheets_table.php`
- Create: `app/Models/Worksheet.php`
- Create: `database/factories/WorksheetFactory.php`
- Test: `tests/Feature/WorksheetModelTest.php`

**Interfaces:**
- Consumes: the payload shape from Task 3.
- Produces:
  - `App\Models\Worksheet` with `$casts` including `payload => 'array'`, `calibration_date => 'date'`, `status` and `conclusion` enums, `source_workbook_path`, and relations `device(): BelongsTo`.
  - `Worksheet::STATUS_DRAFT`, `Worksheet::STATUS_GENERATED`.
  - `Worksheet::CONCLUSION_LAIK`, `Worksheet::CONCLUSION_TIDAK_LAIK`.
  - `WorksheetFactory` with states `generated()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts the payload to an array', function () {
    $worksheet = Worksheet::factory()->create([
        'payload' => ['device' => ['serial' => 'A250705-510']],
    ]);

    expect($worksheet->fresh()->payload)->toBeArray()
        ->and($worksheet->fresh()->payload['device']['serial'])->toBe('A250705-510');
});

it('casts calibration_date to a date', function () {
    $worksheet = Worksheet::factory()->create(['calibration_date' => '2026-07-02']);

    expect($worksheet->fresh()->calibration_date)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('defaults status to draft', function () {
    expect(Worksheet::factory()->create()->status)->toBe(Worksheet::STATUS_DRAFT);
});

it('allows a null payload for a failed parse', function () {
    expect(Worksheet::factory()->create(['payload' => null])->payload)->toBeNull();
});

it('belongs to a device', function () {
    $worksheet = Worksheet::factory()->create();

    expect($worksheet->device)->toBeInstanceOf(\App\Models\Device::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WorksheetModelTest`
Expected: FAIL — `Class "App\Models\Worksheet" not found`.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_worksheets_table --no-interaction
```

Columns, in this order:

```
id
device_id              foreignId nullable, constrained('devices'), nullOnDelete
source_workbook_path   string
original_filename      string
payload                json nullable
cert_number            string nullable
order_number           string nullable
status                 enum draft|generated, default draft
certificate_pdf_path   string nullable
device_serial          string nullable
customer_name          string nullable
calibration_date       date nullable
conclusion             enum Laik Pakai|Tidak Laik Pakai, nullable
timestamps
index (device_id, calibration_date)
index (status)
```

- [ ] **Step 4: Create the model and factory**

```bash
php artisan make:model Worksheet --factory --no-interaction
```

`Worksheet` uses `$guarded = ['id']` (matching `Device`/`Customer` in this codebase), declares the two status and two conclusion constants, and casts `payload => 'array'`, `calibration_date => 'date'`.

`WorksheetFactory` must satisfy the `NOT NULL` columns: `source_workbook_path`, `original_filename`. It must **not** create a `Device` by default — `device_id` is nullable, so leave it null unless a test passes one.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=WorksheetModelTest`
Expected: PASS, 5 tests.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Worksheet.php database/factories/WorksheetFactory.php tests/Feature/WorksheetModelTest.php
git commit -m "feat(worksheets): add worksheets table and model"
```

---

### Task 5: WorksheetService — parse, persist, match device

**Files:**
- Create: `app/Services/WorksheetService.php`
- Test: `tests/Feature/WorksheetServiceTest.php`

**Interfaces:**
- Consumes: `WorksheetParser::parse(string $path): array`, `App\Models\Worksheet`.
- Produces:
  - `WorksheetService::createFromUpload(array $data): Worksheet` where `$data` is `['file_path' => string, 'original_filename' => string, 'device_id' => ?int, 'cert_number' => ?string, 'order_number' => ?string]`.
  - `WorksheetService::reparse(Worksheet $worksheet): Worksheet`
  - `WorksheetService::matchDevice(?string $serial): ?Device`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Device;
use App\Models\Worksheet;
use App\Services\WorksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fixturePath(): string
{
    return __DIR__.'/../Fixtures/autoclave.xlsx';
}

it('creates a worksheet with a populated payload', function () {
    $worksheet = app(WorksheetService::class)->createFromUpload([
        'file_path' => fixturePath(),
        'original_filename' => 'Autoclave.xlsx',
    ]);

    expect($worksheet->payload)->toBeArray()
        ->and($worksheet->payload['device']['serial'])->toBe('A250705-510')
        ->and($worksheet->device_serial)->toBe('A250705-510')
        ->and($worksheet->customer_name)->toBe('Klinik Pratama Ocean Dental Radio Dalam')
        ->and($worksheet->conclusion)->toBe(Worksheet::CONCLUSION_TIDAK_LAIK)
        ->and($worksheet->status)->toBe(Worksheet::STATUS_DRAFT);
});

it('auto-matches the device by serial number', function () {
    $device = Device::factory()->create(['serial_number' => 'A250705-510']);

    $worksheet = app(WorksheetService::class)->createFromUpload([
        'file_path' => fixturePath(),
        'original_filename' => 'Autoclave.xlsx',
    ]);

    expect($worksheet->device_id)->toBe($device->id);
});

it('honours an explicitly chosen device over the serial match', function () {
    Device::factory()->create(['serial_number' => 'A250705-510']);
    $chosen = Device::factory()->create(['serial_number' => 'OTHER']);

    $worksheet = app(WorksheetService::class)->createFromUpload([
        'file_path' => fixturePath(),
        'original_filename' => 'Autoclave.xlsx',
        'device_id' => $chosen->id,
    ]);

    expect($worksheet->device_id)->toBe($chosen->id);
});

it('still saves the record when parsing fails', function () {
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('WRONG');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    $worksheet = app(WorksheetService::class)->createFromUpload([
        'file_path' => $path,
        'original_filename' => 'bad.xlsx',
    ]);

    expect($worksheet->exists)->toBeTrue()
        ->and($worksheet->payload)->toBeNull()
        ->and($worksheet->device_serial)->toBeNull();

    unlink($path);
});

it('reparses a stored workbook in place', function () {
    $worksheet = app(WorksheetService::class)->createFromUpload([
        'file_path' => fixturePath(),
        'original_filename' => 'Autoclave.xlsx',
    ]);

    $worksheet->update(['payload' => null, 'device_serial' => null]);
    $worksheet = app(WorksheetService::class)->reparse($worksheet);

    expect($worksheet->payload)->toBeArray()
        ->and($worksheet->device_serial)->toBe('A250705-510');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WorksheetServiceTest`
Expected: FAIL — `Class "App\Services\WorksheetService" not found`.

- [ ] **Step 3: Implement `WorksheetService`**

`createFromUpload()` calls `WorksheetParser::parse()`, then persists. When the payload is empty, store `payload = null` and leave the denormalised columns null; do **not** throw.

Device resolution order: an explicit `device_id` in `$data` wins; otherwise `matchDevice($payload['device']['serial'])`; otherwise null.

`matchDevice()` returns the first `Device` whose `serial_number` matches, or null.

`reparse()` re-runs `parse()` against `$worksheet->source_workbook_path`, overwrites `payload`, and refreshes the four denormalised columns.

Denormalised values come from the payload: `device_serial` ← `device.serial`, `customer_name` ← `customer.name`, `calibration_date` ← parsed `calibration.calibration_date`, `conclusion` ← `conclusion`.

`calibration.calibration_date` arrives as `02 JUL 2026`. Parse it with `Carbon::createFromFormat('d M Y', $v)` and fall back to `null` on failure.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=WorksheetServiceTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/WorksheetService.php tests/Feature/WorksheetServiceTest.php
git commit -m "feat(worksheets): add service to parse, persist, and match devices"
```

---

### Task 6: Filament resource — model, form, table

**Files:**
- Create: `app/Filament/Dashboard/Resources/Worksheets/WorksheetResource.php`
- Create: `app/Filament/Dashboard/Resources/Worksheets/Schemas/WorksheetForm.php`
- Create: `app/Filament/Dashboard/Resources/Worksheets/Tables/WorksheetsTable.php`
- Create: `app/Filament/Dashboard/Resources/Worksheets/Pages/ListWorksheets.php`
- Create: `app/Filament/Dashboard/Resources/Worksheets/Pages/CreateWorksheet.php`
- Test: `tests/Feature/Filament/WorksheetResourceTest.php`

**Interfaces:**
- Consumes: `WorksheetService::createFromUpload(array $data): Worksheet`.
- Produces: routes at `/dashboard/worksheets`, navigation group `Kalibrasi`, model label `Lembar Kerja`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Filament\Dashboard\Resources\Worksheets\Pages\CreateWorksheet;
use App\Filament\Dashboard\Resources\Worksheets\Pages\ListWorksheets;
use App\Models\Device;
use App\Models\User;
use App\Models\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['Super Admin', 'Admin', 'Hospital Admin', 'Technician'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

function actingAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    return $user;
}

it('lists worksheets', function () {
    Livewire::actingAs(actingAdmin())
        ->test(ListWorksheets::class)
        ->assertSuccessful();
});

it('creates a worksheet from an uploaded workbook', function () {
    Device::factory()->create(['serial_number' => 'A250705-510']);

    Livewire::actingAs(actingAdmin())
        ->test(CreateWorksheet::class)
        ->fillForm([
            'file' => UploadedFile::fake()->createWithContent(
                'Autoclave.xlsx',
                file_get_contents(__DIR__.'/../../Fixtures/autoclave.xlsx')
            ),
            'cert_number' => 'RKS/26/3125',
            'order_number' => '07260074',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $worksheet = Worksheet::firstOrFail();

    expect($worksheet->payload)->toBeArray()
        ->and($worksheet->device_serial)->toBe('A250705-510')
        ->and($worksheet->cert_number)->toBe('RKS/26/3125')
        ->and($worksheet->device)->not->toBeNull();
});

it('shows a danger notification when the workbook cannot be parsed', function () {
    Livewire::actingAs(actingAdmin())
        ->test(CreateWorksheet::class)
        ->fillForm([
            'file' => UploadedFile::fake()->createWithContent('bad.xlsx', 'not a workbook'),
        ])
        ->call('create');

    expect(Worksheet::count())->toBe(1)
        ->and(Worksheet::first()->payload)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WorksheetResourceTest`
Expected: FAIL — `Class "App\Filament\Dashboard\Resources\Worksheets\Pages\CreateWorksheet" not found`.

- [ ] **Step 3: Create the resource**

Follow the existing `BackupsResource` layout for the resource class shape and the deleted `CalibrationWorksheets` resource for the group/label conventions. `WorksheetResource` sets `getNavigationGroup()` → `'Kalibrasi'`, `getModelLabel()`/`getPluralModelLabel()`/`getNavigationLabel()` → `'Lembar Kerja'`, and registers pages `index`, `create`, `view`, `edit`.

`WorksheetForm`:

- `FileUpload::make('file')` — `->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])`, `->disk('public')`, `->directory('worksheets')`, `->required()`. Not a model column; handle it in the page's mutate hook.
- `Select::make('device_id')` — `->relationship('device', 'device_number')`, `->searchable()`, `->preload()`, optional.
- `TextInput::make('cert_number')`, `TextInput::make('order_number')` — both optional.

`CreateWorksheet` and the other pages must resolve records by primary key: `App\Models\Worksheet` has no `getRouteKeyName()` override, so `ViewWorksheet` / `EditWorksheet` receive the `id`. Do not copy `Device`'s UUID route key.

`WorksheetsTable`: columns `device_serial`, `customer_name`, `calibration_date` (date `d/m/Y`), `conclusion` (badge: `Laik Pakai` → success, `Tidak Laik Pakai` → danger), `status`, `created_at` (datetime `d/m/Y H:i`). Default sort `created_at` desc. Record actions: view, delete.

- [ ] **Step 4: Wire the create page**

`CreateWorksheet` overrides the create flow: move the uploaded file to the `public` disk under `worksheets/`, resolve its absolute path, and call `WorksheetService::createFromUpload()`. On an empty payload, send a persistent `Notification::make()->danger()` titled `Gagal membaca berkas` naming the workbook, then redirect to the view page anyway — the record must survive so the upload is not lost.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=WorksheetResourceTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Dashboard/Resources/Worksheets tests/Feature/Filament/WorksheetResourceTest.php
git commit -m "feat(worksheets): add Filament resource for workbook upload"
```

---

### Task 7: View page and certificate PDF

**Files:**
- Create: `app/Filament/Dashboard/Resources/Worksheets/Pages/ViewWorksheet.php`
- Create: `app/Filament/Dashboard/Resources/Worksheets/Pages/EditWorksheet.php`
- Create: `app/Filament/Dashboard/Resources/Worksheets/Schemas/WorksheetInfolist.php`
- Create: `resources/views/pdf/certificate.blade.php`
- Test: `tests/Feature/Calibration/CertificatePdfTest.php`

**Interfaces:**
- Consumes: `App\Models\Worksheet` with `payload`, `cert_number`, `order_number`, `device.customer.address`.
- Produces: header actions `reparse` and `generateCertificate`; `Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])` at A4 portrait.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Filament\Dashboard\Resources\Worksheets\Pages\ViewWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\User;
use App\Models\Worksheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['Super Admin', 'Admin', 'Hospital Admin', 'Technician'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

function worksheetFixture(): Worksheet
{
    $customer = Customer::create([
        'name' => 'Klinik Pratama Ocean Dental Radio Dalam',
        'slug' => 'klinik-pratama-ocean-dental-radio-dalam',
        'address' => 'Jl. Radio Dalam Raya No.26, Jakarta Selatan 12140',
    ]);

    $device = Device::factory()->create([
        'serial_number' => 'A250705-510',
        'customer_id' => $customer->id,
    ]);

    return Worksheet::factory()->create([
        'device_id' => $device->id,
        'cert_number' => 'RKS/26/3125',
        'order_number' => '07260074',
        'payload' => [
            'device' => ['name' => 'AUTOCLAVE', 'brand' => 'LOKAL', 'type' => 'YA28X6T/8', 'serial' => 'A250705-510'],
            'customer' => ['name' => 'Klinik Pratama Ocean Dental Radio Dalam'],
            'service' => ['name' => 'Instalasi sterilisasi pusat'],
            'calibration' => [
                'room' => 'STERILISASI',
                'technician' => 'MARSHEL ASYRAF DALTAFIKA',
                'received_date' => '02 JUL 2026',
                'calibration_date' => '02 JUL 2026',
            ],
            'environment' => [
                'temperature' => ['value' => 24.55, 'uncert' => 0.63],
                'humidity' => ['value' => 55.5, 'uncert' => 2.9],
                'main_voltage' => 222,
            ],
            'instruments' => [
                ['name' => 'Electrical Safety Analyzer', 'brand' => 'RIGEL', 'type' => '288 PLUS', 'serial' => '17Q-1323', 'traceability' => 'LK-032-IDN'],
            ],
            'physical' => [['parameter' => 'Badan dan permukaan', 'result' => 'Baik']],
            'electrical' => [['parameter' => 'Resistansi pembumian', 'measured' => 0.08529005530253725, 'unit' => 'Ω', 'limit' => 0.3, 'limit_unit' => 'Ω']],
            'performance' => [
                'temperature' => [['setting' => 134, 'standard' => 135.67030976400375, 'correction' => 1.670309764003747, 'limit' => '± 2 °C']],
                'temperature_uncertainty' => [['value' => 0.06855226743147864, 'unit' => '°C']],
                'time' => [['setting' => 3, 'standard' => 3, 'correction' => 0, 'limit' => '≥ 3 menit']],
                'time_uncertainty' => [['value' => 0, 'unit' => '°C']],
            ],
            'notes' => [['text' => 'Kalibrasi menggunakan Instruksi Kerja (RKS/MT/IK.01-010)']],
            'conclusion' => 'Tidak Laik Pakai',
        ],
    ]);
}

it('renders the view page', function () {
    $worksheet = worksheetFixture();
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    Livewire::actingAs($user)
        ->test(ViewWorksheet::class, ['record' => $worksheet->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('A250705-510');
});

it('renders a three page certificate from the stored payload', function () {
    $worksheet = worksheetFixture()->load('device.customer');

    $dompdf = Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])
        ->setPaper('a4', 'portrait')
        ->getDomPDF();
    $dompdf->render();

    expect($dompdf->output())->toStartWith('%PDF')
        ->and($dompdf->getCanvas()->get_page_count())->toBe(3);
});

it('carries the workbook values into the certificate', function () {
    $worksheet = worksheetFixture()->load('device.customer');

    $html = view('pdf.certificate', ['worksheet' => $worksheet])->render();

    expect($html)
        ->toContain('RKS/26/3125')
        ->toContain('07260074')
        ->toContain('A250705-510')
        ->toContain('YA28X6T/8')
        ->toContain('24.55')
        ->toContain('135.67030976400375')
        ->toContain('TIDAK LAIK PAKAI')
        ->toContain('Jl. Radio Dalam Raya');
});

it('renders without error when the payload is null', function () {
    $worksheet = Worksheet::factory()->create(['payload' => null]);

    expect(view('pdf.certificate', ['worksheet' => $worksheet])->render())->toBeString();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CertificatePdfTest`
Expected: FAIL — view `pdf.certificate` not found.

- [ ] **Step 3: Write the certificate Blade view**

`resources/views/pdf/certificate.blade.php`, three `<div class="page">` blocks at A4 portrait. Reuse the header/footer partials at `resources/views/pdf/partials/`.

- **Page 1** — cert number (`$worksheet->cert_number`), order number (`$worksheet->order_number`), device identity from `payload.device`, customer name from `payload.customer.name` and address from `$worksheet->device?->customer?->address`, the two dates, the conclusion banner, signature block, QR placeholder.
- **Page 2** — sections 1 Identitas Alat, 2 Pelaksanaan Kalibrasi, 3 Kondisi Lingkungan, 4 Daftar Alat (`payload.instruments`), 5 Pemeriksaan Fisik (`payload.physical`), 6 Pengukuran Keselamatan Listrik (`payload.electrical`).
- **Page 3** — section 7 Hasil Kalibrasi (`payload.performance`), 8 Kesimpulan, 9 Keterangan (`payload.notes`).

**Every** payload access must use `data_get($worksheet->payload, 'a.b.c')` with a fallback, so a null payload renders placeholders instead of raising. Use `$worksheet->payload ?? []` as the root.

The conclusion banner uppercases its text, so the stored `Tidak Laik Pakai` renders as `TIDAK LAIK PAKAI`.

- [ ] **Step 4: Create the view and edit pages**

`ViewWorksheet` extends `ViewRecord` and registers `WorksheetInfolist` plus header actions:

- `Actions\EditAction::make()`
- `Actions\Action::make('reparse')->label('Parse Ulang')->requiresConfirmation()` calling `WorksheetService::reparse($this->record)`.
- `Actions\Action::make('generateCertificate')->label('Generate Sertifikat')->color('success')` loading `device.customer`, rendering the view, and returning a streamed download named `Sertifikat_Kalibrasi_{serial}.pdf`.

`EditWorksheet` is required because `WorksheetResource::getPages()` registers an `edit` route; it can extend `EditRecord` with the same form.

`WorksheetInfolist` renders one `Section` per payload section.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=CertificatePdfTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Dashboard/Resources/Worksheets resources/views/pdf/certificate.blade.php tests/Feature/Calibration/CertificatePdfTest.php
git commit -m "feat(worksheets): render a 3-page certificate from the stored payload"
```

---

## Notes for the implementer

- The fixture `tests/Fixtures/autoclave.xlsx` is the source of truth. If an assertion in this plan disagrees with the fixture, the fixture wins — fix the expectation and say so.
- `LEMBAR KERJA` has no address cell. The address comes from `Device->customer->address`; a worksheet without a linked device renders an address placeholder.
- `LAPORAN HASIL!B52` reads `DINYATAKAN TIDAK LAIK PAKAI` in the fixture, but that cell is a live `IF(...)` over the data sheet. Never hardcode the conclusion; always read the cell.
- The other six sheets are deliberately unread. Do not add them to `SHEETS`.
