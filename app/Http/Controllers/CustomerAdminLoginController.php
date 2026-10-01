<?php

namespace App\Http\Controllers;

use App\Filament\Dashboard\Pages\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAdminLoginController extends Controller
{
    /**
     * Log the user in from a signed, time-limited link and send them to their profile.
     */
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        Filament::auth()->login($user);

        $request->session()->regenerate();

        return redirect()->to(EditProfile::getUrl(panel: 'dashboard'));
    }
}
