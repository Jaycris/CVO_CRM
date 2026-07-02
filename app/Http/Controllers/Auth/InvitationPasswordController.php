<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\User;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class InvitationPasswordController extends Controller
{
    public function create(Request $request, User $user): View|RedirectResponse
    {
        if (! $user->invitation_expires_at || $user->invitation_expires_at->isPast()) {
            return redirect()
                ->route('login')
                ->with('status', 'This invitation link has expired. Please contact an administrator.');
        }

        $user->loadMissing('brand');

        return view('auth.invitation-password', [
            'user' => $user,
            'brand' => $user->brand ?? Brand::where('imprint_name', BrandScope::PARENT_BRAND)->first(),
            'storeUrl' => \URL::temporarySignedRoute(
                'invitation.password.store',
                $user->invitation_expires_at,
                ['user' => $user->id]
            ),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if (! $user->invitation_expires_at || $user->invitation_expires_at->isPast()) {
            return redirect()
                ->route('login')
                ->with('status', 'This invitation link has expired. Please contact an administrator.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'password_created_at' => now(),
            'invitation_expires_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been created. You can now log in.');
    }
}
