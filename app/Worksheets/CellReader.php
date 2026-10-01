<?php

namespace App\Worksheets;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads a single cell from a workbook, preferring the value the file already
 * cached. The mapped cells in LAPORAN HASIL are cross-sheet formulas, so the
 * cached result is the only value available without loading every referenced
 * sheet.
 */
class CellReader
{
    private const COORDINATE = '/^[A-Z]{1,3}[0-9]{1,7}$/';

    private const ERROR = '/^#(REF|VALUE|N\/A|DIV\/0|NAME|NULL|NUM)!$/';

    public static function read(Worksheet $sheet, string $coordinate): string|int|float|null
    {
        if (! preg_match(self::COORDINATE, $coordinate)) {
            return null;
        }

        $cell = $sheet->getCell($coordinate);

        $value = self::firstFilled([
            fn () => $cell->getOldCalculatedValue(),
            fn () => self::recalculate($cell),
            fn () => $cell->getValue(),
        ]);

        if (is_string($value) && preg_match(self::ERROR, $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Recomputing throws when the formula references a sheet that was not
     * loaded, which is the normal case for a cache-less workbook.
     */
    private static function recalculate($cell): mixed
    {
        try {
            return $cell->getCalculatedValue();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, callable(): mixed>  $resolvers
     */
    private static function firstFilled(array $resolvers): mixed
    {
        foreach ($resolvers as $resolve) {
            $value = $resolve();

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
