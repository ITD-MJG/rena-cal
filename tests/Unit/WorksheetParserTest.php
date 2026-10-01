<?php

use App\Worksheets\WorksheetParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('SOMETHING ELSE');
    (new Xlsx($spreadsheet))->save($path);

    expect((new WorksheetParser)->parse($path))->toBe([]);

    unlink($path);
});

it('returns null for a formula whose referenced sheet is not loaded', function () {
    // The spec's risk: LAPORAN HASIL references PENGOLAHAN DATA, which the
    // parser never loads. The result is #REF!, which must become null rather
    // than being stored as certificate data.
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $laporan = $spreadsheet->getActiveSheet();
    $laporan->setTitle('LAPORAN HASIL');
    $laporan->setCellValue('G2', "='PENGOLAHAN DATA'!U23");
    $spreadsheet->createSheet()->setTitle('LEMBAR KERJA');
    (new Xlsx($spreadsheet))->save($path);

    $payload = (new WorksheetParser)->parse($path);

    expect($payload)->not->toHaveKey('device');

    unlink($path);
});

it('never stores a formula string as a value', function () {
    // Invariant over the whole fixture: every mapped cell in LAPORAN HASIL is a
    // formula, so no payload value may surface as its own formula text.
    $offenders = [];
    $walk = function (array $node) use (&$offenders, &$walk) {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $walk($value);
            } elseif (is_string($value) && str_starts_with($value, '=')) {
                $offenders[] = $key.' => '.$value;
            }
        }
    };
    $walk(parsed());

    expect($offenders)->toBe([]);
});

it('omits a mapped cell that is empty instead of shifting the next value in', function () {
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $laporan = $spreadsheet->getActiveSheet();
    $laporan->setTitle('LAPORAN HASIL');
    $laporan->setCellValue('G2', 'LOKAL');
    // G3 deliberately absent: a shifted layout must not promote G4 into it.
    $laporan->setCellValue('G4', 'A250705-510');
    $spreadsheet->createSheet()->setTitle('LEMBAR KERJA');
    (new Xlsx($spreadsheet))->save($path);

    $payload = (new WorksheetParser)->parse($path);

    expect($payload['device']['brand'])->toBe('LOKAL')
        ->and($payload['device'])->not->toHaveKey('type')
        ->and($payload['device']['serial'])->toBe('A250705-510');

    unlink($path);
});
