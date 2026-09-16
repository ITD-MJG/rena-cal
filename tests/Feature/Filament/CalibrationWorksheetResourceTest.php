<?php

use App\Calibration\WorksheetHydrator;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\CreateCalibrationWorksheet;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\EditCalibrationWorksheet;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\ListCalibrationWorksheets;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\ViewCalibrationWorksheet;
use App\Models\Brand;
use App\Models\CalibrationWorksheet;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceName;
use App\Models\Service;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['Super Admin', 'Admin', 'Hospital Admin', 'Technician'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

function actingTechnician(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    return $user;
}

function calibrationFixtures(): array
{
    $service = Service::create(['name' => 'Instalasi sterilisasi pusat', 'slug' => 'sterilisasi']);

    $brand = Brand::create(['name' => 'LOKAL', 'slug' => 'lokal']);

    $device = Device::create([
        'deviceId' => Str::uuid(),
        'device_number' => 'RENA-00099',
        'serial_number' => 'A250705-999',
        'brand_id' => $brand->id,
        'type_id' => Type::create(['name' => 'YA28X6T/8', 'slug' => 'ya28x6t-8', 'brand_id' => $brand->id])->id,
        'device_name_id' => DeviceName::create(['name' => 'AUTOCLAVE', 'slug' => 'autoclave'])->id,
        'customer_id' => Customer::create(['name' => 'Klinik Uji', 'slug' => 'klinik-uji'])->id,
    ]);

    return [$device, $service];
}

it('lists calibration worksheets', function () {
    Livewire::actingAs(actingTechnician())
        ->test(ListCalibrationWorksheets::class)
        ->assertSuccessful();
});

it('hydrates template children when a worksheet is created', function () {
    [$device, $service] = calibrationFixtures();

    Livewire::actingAs(actingTechnician())
        ->test(CreateCalibrationWorksheet::class)
        ->fillForm([
            'device_id' => $device->id,
            'service_id' => $service->id,
            'template_key' => 'autoclave',
            'calibration_room' => 'STERILISASI',
            'received_date' => '2026-07-02',
            'calibration_date' => '2026-07-02',
            'work_method' => 'RKS/MT/IK.01-010',
            'instruments' => [
                ['role' => 'data_logger', 'name' => 'High Temperature Data Logger'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $worksheet = CalibrationWorksheet::firstOrFail();

    expect($worksheet->physicalInspections)->toHaveCount(6)
        ->and($worksheet->electricalSafetyTests)->toHaveCount(4)
        ->and($worksheet->performanceMeasurements)->toHaveCount(2)
        ->and($worksheet->performanceMeasurements->first()->readings)->toHaveCount(3)
        ->and($worksheet->template_version)->toBe('2024.1')
        ->and($worksheet->engine_version)->toBe('1.0.0');
});

it('renders the view page with the calculation snapshot', function () {
    [$device, $service] = calibrationFixtures();

    $worksheet = CalibrationWorksheet::create([
        'device_id' => $device->id,
        'service_id' => $service->id,
        'template_key' => 'autoclave',
        'template_version' => '2024.1',
        'engine_version' => '1.0.0',
        'calibration_room' => 'STERILISASI',
        'received_date' => '2026-07-02',
        'calibration_date' => '2026-07-02',
        'technician_name' => 'UJI',
        'work_method' => 'RKS/MT/IK.01-010',
        'device_resolution' => 0.1,
    ]);

    app(WorksheetHydrator::class)->hydrate($worksheet);
    $worksheet->physicalInspections()->update(['result' => true]);
    $worksheet->recalculate();

    Livewire::actingAs(actingTechnician())
        ->test(ViewCalibrationWorksheet::class, ['record' => $worksheet->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Kesimpulan');
});

it('recalculates from the edit page after saving', function () {
    [$device, $service] = calibrationFixtures();

    $worksheet = CalibrationWorksheet::create([
        'device_id' => $device->id,
        'service_id' => $service->id,
        'template_key' => 'autoclave',
        'template_version' => '2024.1',
        'engine_version' => '1.0.0',
        'calibration_room' => 'STERILISASI',
        'received_date' => '2026-07-02',
        'calibration_date' => '2026-07-02',
        'technician_name' => 'UJI',
        'work_method' => 'RKS/MT/IK.01-010',
        'device_resolution' => 0.1,
    ]);

    app(WorksheetHydrator::class)->hydrate($worksheet);

    Livewire::actingAs(actingTechnician())
        ->test(EditCalibrationWorksheet::class, ['record' => $worksheet->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    // All six inspections default to false, so the physical section scores zero
    // and the engine must have written a conclusion.
    $worksheet->refresh();

    expect($worksheet->conclusion)->not->toBeNull()
        ->and($worksheet->calculation_snapshot)->not->toBeNull();
});

it('persists a physical inspection toggle from the edit form', function () {
    [$device, $service] = calibrationFixtures();

    $worksheet = CalibrationWorksheet::create([
        'device_id' => $device->id,
        'service_id' => $service->id,
        'template_key' => 'autoclave',
        'template_version' => '2024.1',
        'engine_version' => '1.0.0',
        'calibration_room' => 'STERILISASI',
        'received_date' => '2026-07-02',
        'calibration_date' => '2026-07-02',
        'technician_name' => 'UJI',
        'work_method' => 'RKS/MT/IK.01-010',
        'device_resolution' => 0.1,
    ]);

    app(WorksheetHydrator::class)->hydrate($worksheet);

    $first = $worksheet->physicalInspections()->orderBy('sort_order')->first();

    $component = Livewire::actingAs(actingTechnician())
        ->test(EditCalibrationWorksheet::class, ['record' => $worksheet->getRouteKey()]);

    // The inspection repeaters must not inherit a disabled state, otherwise the
    // Baik / Tidak Baik toggles cannot be flipped by the technician.
    $key = array_key_first($component->get('data')['physicalInspections']);

    $component
        ->set("data.physicalInspections.{$key}.result", true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($worksheet->fresh()->physicalInspections()->find($first->id)->result)->toBeTrue();
});
