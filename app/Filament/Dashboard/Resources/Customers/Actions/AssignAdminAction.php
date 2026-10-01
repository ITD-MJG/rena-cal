<?php

namespace App\Filament\Dashboard\Resources\Customers\Actions;

use App\Models\User;
use App\Notifications\CustomerAdminCreatedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\DB;

class AssignAdminAction
{
    public static function make(): Action
    {
        return Action::make('assign_admin')
            ->label(__('customers.actions.assign_admin'))
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->form([
                Toggle::make('create_new_user')
                    ->label(__('customers.form.create_new_user.label'))
                    ->reactive(),
                Select::make('user_id')
                    ->label(__('customers.form.select_user.label'))
                    ->options(User::whereNull('customer_id')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn ($get) => ! $get('create_new_user'))
                    ->required(fn ($get) => ! $get('create_new_user')),
                TextInput::make('new_user_name')
                    ->label(__('customers.form.new_user_name.label'))
                    ->visible(fn ($get) => $get('create_new_user'))
                    ->required(fn ($get) => $get('create_new_user')),
                TextInput::make('new_user_email')
                    ->label(__('customers.form.new_user_email.label'))
                    ->email()
                    ->visible(fn ($get) => $get('create_new_user'))
                    ->required(fn ($get) => $get('create_new_user'))
                    ->maxLength(255),
                TextInput::make('new_user_phone')
                    ->label(__('customers.form.new_user_phone.label'))
                    ->tel()
                    ->visible(fn ($get) => $get('create_new_user'))
                    ->maxLength(20),
            ])
            ->action(function (array $data, $record, Action $action) {
                $customer = $record ?? $action->getLivewire()->getOwnerRecord();

                DB::transaction(function () use ($data, $customer) {
                    $user = null;

                    if (! ($data['create_new_user'] ?? false) && isset($data['user_id'])) {
                        $user = User::findOrFail($data['user_id']);
                    } elseif ($data['create_new_user'] ?? false) {
                        $user = User::create([
                            'name' => $data['new_user_name'],
                            'email' => $data['new_user_email'],
                            'phone_number' => $data['new_user_phone'] ?? null,
                            'customer_id' => $customer->id,
                        ]);

                        $user->assignRole('Hospital Admin');
                        $user->notify(new CustomerAdminCreatedNotification($user->createLoginUrl()));
                    }

                    if ($user) {
                        $user->update(['customer_id' => $customer->id]);

                        // Ensure role is assigned if linking existing user
                        if (! $user->hasRole('Hospital Admin')) {
                            $user->assignRole('Hospital Admin');
                        }
                    }
                });
            })
            ->modalHeading(__('customers.actions.assign_admin'))
            ->modalSubmitActionLabel(__('customers.actions.assign_admin'))
            ->successNotificationTitle(function ($record, Action $action) {
                $customer = $record ?? $action->getLivewire()->getOwnerRecord();

                return __('customers.actions.assign_admin_success', ['label' => $customer->name]);
            });
    }
}
