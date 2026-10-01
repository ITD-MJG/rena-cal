<?php

use App\Models\Device;
use App\Models\Worksheet;
use App\Services\WorksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

it('derives calibration_date from the workbook date string', function () {
    $worksheet = app(WorksheetService::class)->createFromUpload([
        'file_path' => fixturePath(),
        'original_filename' => 'Autoclave.xlsx',
    ]);

    expect($worksheet->calibration_date->format('Y-m-d'))->toBe('2026-07-02');
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
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('WRONG');
    (new Xlsx($spreadsheet))->save($path);

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
