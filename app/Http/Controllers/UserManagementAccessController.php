<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementAccessController extends Controller
{
    /**
     * Show the access code verification page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/user-management-access');
    }

    /**
     * Verify the submitted access code.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $configuredCode = config('auth.user_management_access_code');

        if (empty($configuredCode) || ! hash_equals($configuredCode, $request->input('code'))) {
            return back()->withErrors([
                'code' => 'The access code is incorrect.',
            ]);
        }

        $request->session()->put('user_management.verified_at', time());

        return redirect()->intended(route('users.index'));
    }
}
