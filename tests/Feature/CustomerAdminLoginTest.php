<?php

use App\Filament\Dashboard\Pages\EditProfile;
use App\Filament\Dashboard\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Province;
use App\Models\User;
use App\Notifications\CustomerAdminCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeCustomer(): Customer
{
    $category = CustomerCategory::firstOrCreate(['name' => 'RS', 'slug' => 'rs']);
    $province = Province::firstOrCreate(['code' => 31, 'name' => 'Jakarta']);

    return Customer::create([
        'name' => 'Test Hospital',
        'type' => 'Swasta',
        'province_id' => $province->code,
        'categories_id' => $category->id,
    ]);
}

it('uses Calibration2025 as the default password', function () {
    expect(User::DEFAULT_PASSWORD)->toBe('Calibration2025!');
});

it('assigns the default password when a user is created without one', function () {
    $user = User::create([
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
    ]);

    expect(Hash::check('Calibration2025!', $user->password))->toBeTrue();
});

it('displays the default password in the admin created email', function () {
    $customer = makeCustomer();
    $user = User::create([
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'customer_id' => $customer->id,
    ]);

    $mail = (new CustomerAdminCreatedNotification($user->createLoginUrl()))->toMail($user);
    $html = (string) $mail->render();

    expect($html)->toContain('Calibration2025!')
        ->and($html)->toContain('Log In')
        ->and($html)->toContain(e($user->createLoginUrl()));
});

it('logs the user in and redirects to the profile page from a signed link', function () {
    $user = User::create([
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
    ]);

    $response = $this->get($user->createLoginUrl());

    $response->assertRedirect(EditProfile::getUrl(panel: 'dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects an unsigned login link', function () {
    $user = User::create([
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
    ]);

    $this->get(route('customers.admin.login', ['user' => $user->id]))
        ->assertForbidden();

    $this->assertGuest();
});

it('rejects an expired login link', function () {
    $user = User::create([
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'customers.admin.login',
        now()->subMinute(),
        ['user' => $user->id],
    );

    $this->get($url)->assertForbidden();
    $this->assertGuest();
});

it('creates a hospital admin and notifies them from the assign admin action', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'Super Admin']);
    Role::firstOrCreate(['name' => 'Hospital Admin']);

    $customer = makeCustomer();

    $actor = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);
    $actor->assignRole('Super Admin');

    Livewire::actingAs($actor)
        ->test(ListCustomers::class)
        ->mountTableAction('assign_admin', $customer)
        ->set('mountedActions.0.data.create_new_user', true)
        ->set('mountedActions.0.data.new_user_name', 'New Admin')
        ->set('mountedActions.0.data.new_user_email', 'new-admin@example.com')
        ->callMountedTableAction();

    $newAdmin = User::where('email', 'new-admin@example.com')->first();

    expect($newAdmin)->not->toBeNull()
        ->and($newAdmin->customer_id)->toBe($customer->id)
        ->and($newAdmin->hasRole('Hospital Admin'))->toBeTrue()
        ->and(Hash::check('Calibration2025!', $newAdmin->password))->toBeTrue();

    Notification::assertSentTo($newAdmin, CustomerAdminCreatedNotification::class);
});
