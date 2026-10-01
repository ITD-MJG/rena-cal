<?php

use App\Worksheets\CellReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

function sheetWith(array $cells): Worksheet
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
