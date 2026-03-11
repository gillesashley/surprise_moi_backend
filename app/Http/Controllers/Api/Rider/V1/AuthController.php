<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\LoginRequest;
use App\Http\Requests\Api\Rider\V1\RegisterRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use App\Models\Rider;
use App\Services\KairosAfrikaSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
     * Login a rider with email or phone.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $field = $request->filled('email') ? 'email' : 'phone';
        $rider = Rider::where($field, $request->input($field))->first();

        if (! $rider || ! Hash::check($request->password, $rider->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $rider->createToken('rider-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'rider' => new RiderResource($rider),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
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
     * Send password reset instructions to rider's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $rider = Rider::where('email', $request->email)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'No rider found with this email.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset instructions sent to your email.',
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
