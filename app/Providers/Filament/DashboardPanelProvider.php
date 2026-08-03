<?php

namespace App\Providers\Filament;

use App\Filament\Dashboard\Pages\Auth\CustomPasswordReset;
use App\Filament\Dashboard\Pages\EditProfile;
use App\Filament\Dashboard\Resources\Devices\Widgets\DevicesWidget;
use App\Filament\Pages\Dashboard;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use YousefAman\FilamentAutosave\AutosavePlugin;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('dashboard')
            ->path('dashboard')
            ->login()
            ->spa()
            ->passwordReset(RequestPasswordReset::class, CustomPasswordReset::class)
            ->defaultThemeMode(ThemeMode::Light)
            ->viteTheme('resources/css/filament/dashboard/theme.css')
            ->brandName('Rena')
            ->brandLogo(fn () => asset('assets/images/logos/Rena-Logo.webp'))
            ->brandLogoHeight('4em')
            ->favicon(fn () => asset('assets/images/logos/Rena-Logo.webp'))
            ->profile(EditProfile::class)
            ->maxContentWidth(Width::Full)
            ->colors([
                'primary' => Color::Red,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn () => auth()->user()->name)
                    ->url(fn () => EditProfile::getUrl())
                    ->icon(Heroicon::UserCircle),
            ])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('navigation.Devices')),
                NavigationGroup::make('Kalibrasi'),
                NavigationGroup::make(fn () => __('navigation.Admin Management')),
                NavigationGroup::make(fn () => __('navigation.User Management')),
            ])
            ->discoverResources(in: app_path('Filament/Dashboard/Resources'), for: 'App\Filament\Dashboard\Resources')
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\Filament\Dashboard\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Dashboard/Widgets'), for: 'App\Filament\Dashboard\Widgets')
            ->widgets([
                DevicesWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                AutosavePlugin::make(),
            ]);
    }
}
