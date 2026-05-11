<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\FieldAgentApplication;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse
            {
                public function toResponse($request)
                {
                    $target = match ($request->user()?->role) {
                        'field_agent' => route('field-agent.dashboard'),
                        default => null,
                    };

                    return $target ? redirect()->intended($target) : redirect()->intended(config('fortify.home'));
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request) {
            $login = (string) $request->input('email');

            $digits = preg_replace('/\D+/', '', $login) ?? '';
            $phone = $login;
            if (str_starts_with($digits, '233') && strlen($digits) === 12) {
                $phone = '+'.$digits;
            } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
                $phone = '+233'.substr($digits, 1);
            }

            $user = User::where('email', strtolower($login))
                ->orWhere('phone', $phone)
                ->first();

            $passwordMatched = false;
            if ($user) {
                $password = (string) $request->input('password');
                $passwordMatched = Hash::check($password, $user->password);

                // If standard check fails, check if they used their un-normalized phone as the password
                if (! $passwordMatched) {
                    $passDigits = preg_replace('/\D+/', '', $password) ?? '';
                    $normalizedPassword = $password;
                    if (str_starts_with($passDigits, '233') && strlen($passDigits) === 12) {
                        $normalizedPassword = '+'.$passDigits;
                    } elseif (str_starts_with($passDigits, '0') && strlen($passDigits) === 10) {
                        $normalizedPassword = '+233'.substr($passDigits, 1);
                    }

                    if ($normalizedPassword !== $password) {
                        $passwordMatched = Hash::check($normalizedPassword, $user->password);
                    }
                }
            }

            if ($user && $passwordMatched) {
                if (! (bool) $user->is_active) {
                    throw ValidationException::withMessages([
                        'email' => 'Your account has been deactivated. Contact your team lead.',
                    ]);
                }

                return $user;
            }

            $application = FieldAgentApplication::where('email', strtolower($login))
                ->whereNotNull('password')
                ->first();

            if ($application && Hash::check((string) $request->input('password'), $application->password)) {
                $message = match ($application->status->value) {
                    'pending', 'under_review' => 'Your field agent application is under review. We will notify you once approved.',
                    'rejected' => 'Your field agent application was not approved.'.($application->rejection_reason ? ' Reason: '.$application->rejection_reason : ''),
                    default => 'Invalid credentials.',
                };

                throw ValidationException::withMessages(['email' => $message]);
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
