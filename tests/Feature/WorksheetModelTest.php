<?php

use App\Models\Device;
use App\Models\Worksheet;
use Carbon\CarbonInterface;
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

    expect($worksheet->fresh()->calibration_date)->toBeInstanceOf(CarbonInterface::class);
});

it('defaults status to draft', function () {
    expect(Worksheet::factory()->create()->status)->toBe(Worksheet::STATUS_DRAFT);
});

it('allows a null payload for a failed parse', function () {
    expect(Worksheet::factory()->create(['payload' => null])->payload)->toBeNull();
});

it('belongs to a device', function () {
    $worksheet = Worksheet::factory()->create(['device_id' => Device::factory()]);

    expect($worksheet->device)->toBeInstanceOf(Device::class);
});
