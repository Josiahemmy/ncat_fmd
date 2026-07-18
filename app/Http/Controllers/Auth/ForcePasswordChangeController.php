<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    /** The branded "set a new password" screen. */
    public function edit(): Response
    {
        return Inertia::render('Auth/ForcePasswordChange');
    }

    /** Set the new password, clear the flag, and audit it. */
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['password' => PasswordPolicy::rules()]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'password_change_required' => false,
        ])->save();

        activity('auth')->causedBy($user)->event('password_changed')
            ->log('Set a new password on first sign-in');

        return redirect()->intended(route('dashboard'))
            ->with('status', 'Your password has been updated.');
    }
}
