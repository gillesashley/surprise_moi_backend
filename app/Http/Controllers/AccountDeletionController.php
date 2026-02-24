<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountDeletionController extends Controller
{
    public function show()
    {
        return Inertia::render('account-deletion');
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $user->delete();

            return redirect()->route('account-deletion.show')
                ->with('status', 'Your account and all associated data have been deleted successfully.');
        }

        return redirect()->route('account-deletion.show')
            ->with('status', 'If an account with this email exists, it has been deleted.');
    }
}
