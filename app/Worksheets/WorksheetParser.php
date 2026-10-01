<?php

namespace App\Worksheets;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Reads a filled calibration workbook into the payload the certificate renders
 * from.
 *
 * LAPORAN HASIL is a summary sheet: nearly every cell is a cross-sheet formula
 * whose result the file already cached, so CellReader reads those cached values
 * rather than recomputing. LEMBAR KERJA supplies the page-1 identity block,
 * which LAPORAN HASIL does not carry.
 *
 * The map is coordinate-bound on purpose: every workbook uses the same layout.
 * A layout change fails the parser test loudly rather than shifting values.
 */
class WorksheetParser
{
    public const SHEETS = ['LAPORAN HASIL', 'LEMBAR KERJA'];

    /**
     * A summary cell holding the verdict. It reads "DINYATAKAN TIDAK LAIK PAKAI"
     * or "DINYATAKAN LAIK PAKAI".
     */
    private const CONCLUSION = ['LAPORAN HASIL', 'B52'];

    /**
     * Single-value cells: payload path => [sheet, coordinate].
     *
     * @var array<string, array<string, string>>
     */
    private const SCALARS = [
        'LAPORAN HASIL' => [
            'device.brand' => 'G2',
            'device.type' => 'G3',
            'device.serial' => 'G4',
            'service.name' => 'G7',
            'calibration.room' => 'G8',
            'calibration.technician' => 'G9',
            'environment.temperature.value' => 'H12',
            'environment.temperature.uncert' => 'J12',
            'environment.humidity.value' => 'H13',
            'environment.humidity.uncert' => 'J13',
            'environment.main_voltage' => 'J14',
        ],
        'LEMBAR KERJA' => [
            'device.name' => 'I3',
            'customer.name' => 'F7',
            'calibration.received_date' => 'S9',
            'calibration.calibration_date' => 'S10',
        ],
    ];

    /**
     * Repeating row blocks: payload path => [sheet, first row, last row, columns].
     * A row is skipped when its first declared column is empty.
     *
     * @var array<string, array{0: string, 1: int, 2: int, 3: array<string, string>}>
     */
    private const ROWS = [
        'instruments' => ['LAPORAN HASIL', 18, 20,
            ['name' => 'C', 'brand' => 'I', 'type' => 'M', 'serial' => 'Q', 'traceability' => 'U']],
        'physical' => ['LAPORAN HASIL', 24, 29,
            ['parameter' => 'C', 'result' => 'J']],
        'electrical' => ['LAPORAN HASIL', 33, 36,
            ['parameter' => 'C', 'measured' => 'O', 'unit' => 'R', 'limit' => 'U', 'limit_unit' => 'X']],
        'performance.temperature' => ['LAPORAN HASIL', 42, 42,
            ['setting' => 'B', 'standard' => 'I', 'correction' => 'O', 'limit' => 'T']],
        'performance.temperature_uncertainty' => ['LAPORAN HASIL', 43, 43,
            ['value' => 'O', 'unit' => 'P']],
        'performance.time' => ['LAPORAN HASIL', 48, 48,
            ['setting' => 'B', 'standard' => 'I', 'correction' => 'O', 'limit' => 'T']],
        'performance.time_uncertainty' => ['LAPORAN HASIL', 49, 49,
            ['value' => 'O', 'unit' => 'P']],
        'notes' => ['LAPORAN HASIL', 55, 58, ['text' => 'C']],
    ];

    /**
     * Strings that must stay strings even though they look numeric.
     */
    private const KEEP_AS_STRING = ['unit', 'limit', 'limit_unit', 'received_date', 'calibration_date'];

    /**
     * @return array<string, mixed>
     */
    public function parse(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $spreadsheet = $this->load($path);

        if ($spreadsheet === null) {
            return [];
        }

        $payload = [];

        foreach (self::SCALARS as $sheetName => $cells) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            foreach ($cells as $key => $coordinate) {
                $value = $this->clean(CellReader::read($sheet, $coordinate), $key);

                if ($value !== null) {
                    data_set($payload, $key, $value);
                }
            }
        }

        foreach (self::ROWS as $key => $block) {
            $rows = $this->readRows($spreadsheet, $block);

            if ($rows !== []) {
                data_set($payload, $key, $rows);
            }
        }

        $payload['conclusion'] = $this->conclusion($spreadsheet);

        return $payload;
    }

    private function load(string $path): ?Spreadsheet
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(self::SHEETS);

            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            return null;
        }

        foreach (self::SHEETS as $name) {
            if ($spreadsheet->getSheetByName($name) === null) {
                return null;
            }
        }

        return $spreadsheet;
    }

    /**
     * @param  array{0: string, 1: int, 2: int, 3: array<string, string>}  $block
     * @return array<int, array<string, mixed>>
     */
    private function readRows(Spreadsheet $spreadsheet, array $block): array
    {
        [$sheetName, $firstRow, $lastRow, $columns] = $block;
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $firstColumn = reset($columns);
        $rows = [];

        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $lead = $this->clean(CellReader::read($sheet, $firstColumn.$row), $firstColumn);

            if ($lead === null) {
                continue;
            }

            $entry = [];

            foreach ($columns as $field => $column) {
                $entry[$field] = $this->clean(CellReader::read($sheet, $column.$row), $field);
            }

            $rows[] = $entry;
        }

        return $rows;
    }

    private function conclusion(Spreadsheet $spreadsheet): ?string
    {
        [$sheetName, $coordinate] = self::CONCLUSION;

        $raw = CellReader::read($spreadsheet->getSheetByName($sheetName), $coordinate);

        if (! is_string($raw)) {
            return null;
        }

        $verdict = strtoupper(trim(preg_replace('/^DINYATAKAN\s+/i', '', $this->collapse($raw))));

        return match ($verdict) {
            'LAIK PAKAI' => 'Laik Pakai',
            'TIDAK LAIK PAKAI' => 'Tidak Laik Pakai',
            default => null,
        };
    }

    /**
     * Trim, collapse internal whitespace runs, and cast a numeric string to a
     * number unless the field is known to hold a unit or a date.
     */
    private function clean(mixed $value, string $field): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $text = $this->collapse($value);

        if ($text === '') {
            return null;
        }

        $leaf = substr($field, (int) strrpos('.'.$field, '.'));

        if (in_array($leaf, self::KEEP_AS_STRING, true)) {
            return $text;
        }

        if (is_numeric($text)) {
            return $text + 0;
        }

        return $text;
    }

    private function collapse(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
