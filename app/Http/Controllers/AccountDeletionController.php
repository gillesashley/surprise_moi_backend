<?php

namespace App\Http\Controllers;

use App\Mail\AccountDeletionConfirmation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccountDeletionController extends Controller
{
    private const CONFIRMATION_TTL_MINUTES = 60;

    public function show(): InertiaResponse
    {
        return Inertia::render('account-deletion');
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $confirmationUrl = URL::temporarySignedRoute(
                'account-deletion.confirm',
                Carbon::now()->addMinutes(self::CONFIRMATION_TTL_MINUTES),
                ['user' => $user->id]
            );

            Mail::to($user->email)->queue(
                new AccountDeletionConfirmation($user, $confirmationUrl)
            );
        }

        return redirect()->route('account-deletion.show')
            ->with('status', 'If an account with this email exists, we have sent a confirmation link. Please check your inbox to complete the deletion.');
    }

    public function showConfirmation(Request $request, User $user): Response
    {
        return response()->view('account-deletion.confirm', [
            'user' => $user,
            'confirmFormUrl' => $request->fullUrl(),
        ]);
    }

    public function confirm(Request $request, User $user): Response
    {
        $user->delete();

        return response()->view('account-deletion.deleted');
    }
}
