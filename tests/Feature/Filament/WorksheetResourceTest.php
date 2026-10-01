<?php

use App\Filament\Dashboard\Resources\Worksheets\Pages\CreateWorksheet;
use App\Filament\Dashboard\Resources\Worksheets\Pages\EditWorksheet;
use App\Filament\Dashboard\Resources\Worksheets\Pages\ListWorksheets;
use App\Filament\Dashboard\Resources\Worksheets\Schemas\WorksheetForm;
use App\Models\Customer;
use App\Models\Device;
use App\Models\DeviceName;
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

function fakeWorkbook(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'Autoclave.xlsx',
        file_get_contents(__DIR__.'/../../Fixtures/autoclave.xlsx')
    );
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
            'file' => fakeWorkbook(),
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

it('saves the record and warns when the workbook cannot be parsed', function () {
    Livewire::actingAs(actingAdmin())
        ->test(CreateWorksheet::class)
        ->fillForm([
            'file' => UploadedFile::fake()->createWithContent('bad.xlsx', 'not a workbook'),
        ])
        ->call('create');

    expect(Worksheet::count())->toBe(1)
        ->and(Worksheet::first()->payload)->toBeNull();
});

it('denies a user with no role', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ListWorksheets::class)
        ->assertForbidden();
});

it('denies a hospital admin worksheets belonging to another customer', function () {
    $otherCustomer = Customer::create([
        'name' => 'Other Clinic',
        'slug' => 'other-clinic',
    ]);
    $worksheet = Worksheet::factory()->create([
        'customer_name' => 'Other Clinic',
        'device_id' => Device::factory()->create(['customer_id' => $otherCustomer->id]),
    ]);

    $user = User::factory()->create(['customer_id' => Customer::create([
        'name' => 'My Clinic',
        'slug' => 'my-clinic',
    ])->id]);
    $user->assignRole('Hospital Admin');

    expect($user->can('view', $worksheet))->toBeFalse();
});

it('allows a technician to view worksheets', function () {
    $user = User::factory()->create();
    $user->assignRole('Technician');

    Livewire::actingAs($user)
        ->test(ListWorksheets::class)
        ->assertSuccessful();
});

it('saves an edit without re-uploading the workbook', function () {
    $worksheet = Worksheet::factory()->create(['cert_number' => 'OLD']);

    Livewire::actingAs(actingAdmin())
        ->test(EditWorksheet::class, ['record' => $worksheet->getRouteKey()])
        ->fillForm(['cert_number' => 'NEW'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($worksheet->fresh()->cert_number)->toBe('NEW');
});

it('stores the uploaded filename, not the temporary path', function () {
    Livewire::actingAs(actingAdmin())
        ->test(CreateWorksheet::class)
        ->fillForm(['file' => fakeWorkbook()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Worksheet::firstOrFail()->original_filename)->toBe('Autoclave.xlsx');
});

it('scopes the table to the hospital admin own customer', function () {
    $mine = Customer::create(['name' => 'My Clinic', 'slug' => 'my-clinic-scope']);
    $theirs = Customer::create(['name' => 'Other Clinic', 'slug' => 'other-clinic-scope']);

    $myDevice = Device::factory()->create(['customer_id' => $mine->id]);
    $theirDevice = Device::factory()->create(['customer_id' => $theirs->id]);

    Worksheet::factory()->create(['device_id' => $myDevice->id, 'customer_name' => 'My Clinic']);
    Worksheet::factory()->create(['device_id' => $theirDevice->id, 'customer_name' => 'Other Clinic']);

    $user = User::factory()->create(['customer_id' => $mine->id]);
    $user->assignRole('Hospital Admin');

    Livewire::actingAs($user)
        ->test(ListWorksheets::class)
        ->assertCanSeeTableRecords(Worksheet::where('customer_name', 'My Clinic')->get())
        ->assertCanNotSeeTableRecords(Worksheet::where('customer_name', 'Other Clinic')->get());
});

it('shows every worksheet to staff roles', function () {
    $a = Customer::create(['name' => 'Clinic A', 'slug' => 'clinic-a-staff']);
    $b = Customer::create(['name' => 'Clinic B', 'slug' => 'clinic-b-staff']);

    Worksheet::factory()->create(['device_id' => Device::factory()->create(['customer_id' => $a->id]), 'customer_name' => 'Clinic A']);
    Worksheet::factory()->create(['device_id' => Device::factory()->create(['customer_id' => $b->id]), 'customer_name' => 'Clinic B']);

    $user = User::factory()->create();
    $user->assignRole('Technician');

    Livewire::actingAs($user)
        ->test(ListWorksheets::class)
        ->assertCanSeeTableRecords(Worksheet::all());
});

it('shows the device name between the device code and serial number', function () {
    $device = Device::factory()->create([
        'device_number' => 'RENA-00001',
        'serial_number' => 'A250705-510',
        'device_name_id' => DeviceName::create(['name' => 'AUTOCLAVE', 'slug' => 'autoclave-label'])->id,
    ]);

    $label = WorksheetForm::deviceOptionLabel($device);

    expect($label)->toBe('RENA-00001 — AUTOCLAVE — A250705-510');
});

it('omits a missing device name from the option label', function () {
    $device = Device::factory()->create([
        'device_number' => 'RENA-00002',
        'serial_number' => 'SN-2',
        'device_name_id' => null,
    ]);

    $label = WorksheetForm::deviceOptionLabel($device);

    expect($label)->toBe('RENA-00002 — SN-2');
});
