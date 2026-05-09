<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Actions\Rider\ProvisionShadowRiderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\LoginRequest;
use App\Http\Requests\Api\Rider\V1\RegisterRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use App\Models\Rider;
use App\Models\User;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(protected KairosAfrikaSmsService $smsService) {}

    /**
     * Register a new rider.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $rider = Rider::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'vehicle_category' => $request->vehicle_category,
            'status' => 'pending',
        ]);

        $token = $rider->createToken('rider-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please upload your documents for verification.',
            'data' => [
                'rider' => new RiderResource($rider),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login a rider with email or phone. When config('rider.admin_login_enabled')
     * is true, also accepts super-admin / admin credentials from the users table
     * and idempotently provisions a shadow rider.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $phone = $request->input('phone');
        $password = $request->input('password');

        $rider = $email
            ? Rider::where('email', $email)->first()
            : Rider::where('phone', $phone)->first();

        if ($rider) {
            return $this->authenticateExistingRider($rider, $password);
        }

        if ($email && config('rider.admin_login_enabled')) {
            $admin = User::where('email', $email)
                ->whereIn('role', ['super_admin', 'admin'])
                ->first();

            if ($admin && Hash::check($password, $admin->password)) {
                $shadowRider = (new ProvisionShadowRiderAction)($admin);

                return $this->issueLoginResponse($shadowRider, 'Login successful.');
            }
        }

        return $this->invalidCredentialsResponse();
    }

    /**
     * Handle a Rider row that matched the email/phone lookup.
     */
    protected function authenticateExistingRider(Rider $rider, string $password): JsonResponse
    {
        if ($rider->isShadowRider()) {
            if (! config('rider.admin_login_enabled')) {
                return $this->invalidCredentialsResponse();
            }

            $admin = User::where('id', $rider->user_id)
                ->whereIn('role', ['super_admin', 'admin'])
                ->first();

            if (! $admin || ! Hash::check($password, $admin->password)) {
                return $this->invalidCredentialsResponse();
            }

            return $this->issueLoginResponse($rider, 'Login successful.');
        }

        if (! Hash::check($password, $rider->password)) {
            return $this->invalidCredentialsResponse();
        }

        if ($rider->isSuspended()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        if ($rider->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Your application was rejected. Please contact support for details.',
            ], 403);
        }

        if (! $rider->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is currently deactivated. Please contact support.',
            ], 403);
        }

        return $this->issueLoginResponse($rider, 'Login successful.');
    }

    protected function issueLoginResponse(Rider $rider, string $message): JsonResponse
    {
        $token = $rider->createToken('rider-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'rider' => new RiderResource($rider),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    protected function invalidCredentialsResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials.',
        ], 401);
    }

    /**
     * Logout the authenticated rider (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user('rider')->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Send OTP to a rider's phone number.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);

        $rider = Rider::where('phone', $request->phone)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'No rider found with this phone number.',
            ], 404);
        }

        $this->smsService->sendOtp($rider->phone);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
        ]);
    }

    /**
     * Verify OTP for a rider's phone number.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        $result = $this->smsService->validateOtp($request->otp, $request->phone);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $rider = Rider::where('phone', $request->phone)->first();
        if ($rider) {
            $rider->update(['phone_verified_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully.',
        ]);
    }

    /**
     * Send password reset instructions via the riders password broker.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $rider = Rider::where('email', $request->email)->first();
        if ($rider && $rider->isShadowRider()) {
            return response()->json([
                'success' => false,
                'message' => 'This account uses dashboard credentials. Reset your password in the admin dashboard.',
            ], 422);
        }

        Password::broker('riders')->sendResetLink(['email' => $request->email]);

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with this email, a reset link has been sent.',
        ]);
    }

    /**
     * Reset rider's password with token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
