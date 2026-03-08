<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResendOtpRequest;
use App\Http\Requests\Api\V1\Auth\ResendVerificationRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
use App\Http\Requests\Api\V1\Auth\VerifyPhoneRequest;
use App\Jobs\SendPasswordResetToken;
use App\Jobs\SendVerificationEmail;
use App\Models\User;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected KairosAfrikaSmsService $smsService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'role' => $request->role,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
        ]);

        // Send OTP to phone number for verification
        $otpResult = $this->smsService->sendOtp($user->phone);

        // Send email verification notification via queue
        dispatch(new SendVerificationEmail($user));

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully. Please verify your phone number with the OTP sent to you.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'phone_verified_at' => $user->phone_verified_at,
                    'created_at' => $user->created_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'otp_sent' => $otpResult['success'],
            ],
        ], 201);
    }

    /**
     * Verify phone number with OTP.
     */
    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->phone_verified_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already verified',
            ], 400);
        }

        // Validate OTP with Kairos Afrika
        $validationResult = $this->smsService->validateOtp($request->code, $request->phone);

        if (! $validationResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $validationResult['message'],
            ], 400);
        }

        // Mark phone as verified
        $user->phone_verified_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'phone_verified_at' => $user->phone_verified_at,
                ],
            ],
        ]);
    }

    /**
     * Resend OTP for phone verification.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->phone_verified_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already verified',
            ], 400);
        }

        $otpResult = $this->smsService->sendOtp($user->phone);

        if (! $otpResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $otpResult['message'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent successfully',
        ]);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'phone_verified_at' => $user->phone_verified_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get all user tokens.
     */
    public function tokens(): JsonResponse
    {
        $tokens = auth()->user()->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['tokens' => $tokens],
        ]);
    }

    /**
     * Revoke a specific token.
     */
    public function revokeToken(int $id): JsonResponse
    {
        $deleted = auth()->user()->tokens()->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Revoke all user tokens.
     */
    public function revokeAllTokens(): JsonResponse
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All tokens revoked successfully',
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Dispatch password reset token job to queue
        dispatch(new SendPasswordResetToken($user, $request->email));

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to your email',
        ]);
    }

    /**
     * Reset password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired token',
        ], 400);
    }

    /**
     * Verify email using signed URL.
     *
     * Returns a branded HTML page that auto-redirects to the mobile app
     * via deep link (surprisemoi://email-verified).
     */
    public function verifyEmail(VerifyEmailRequest $request): Response
    {
        $user = User::findOrFail($request->route('id'));
        $deepLink = config('deep_links.scheme').'://email-verified';

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->view('auth.email-verified', [
                'success' => false,
                'title' => 'Invalid Link',
                'message' => 'This verification link is invalid or has expired. Please request a new one from the app.',
                'deepLink' => null,
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->view('auth.email-verified', [
                'success' => true,
                'title' => 'Already Verified',
                'message' => 'Your email address has already been verified. You can continue using the app.',
                'deepLink' => $deepLink,
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->view('auth.email-verified', [
            'success' => true,
            'title' => 'Email Verified!',
            'message' => 'Your email address has been verified successfully. You can now enjoy the full Surprise Moi experience.',
            'deepLink' => $deepLink,
        ]);
    }

    /**
     * Resend email verification code.
     */
    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified',
            ], 400);
        }

        // Send email verification notification via queue
        dispatch(new SendVerificationEmail($user));

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully',
        ]);
    }
}
