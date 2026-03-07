<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Models\Interest;
use App\Models\PersonalityTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get user profile.
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $user->load(['interests', 'personalityTraits']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Update user profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Handle banner upload
        if ($request->hasFile('banner')) {
            // Delete old banner if exists
            if ($user->banner) {
                Storage::disk('public')->delete($user->banner);
            }

            $data['banner'] = $request->file('banner')->store('banners', 'public');
        }

        // Extract interests and personality traits from data
        $interests = $data['interests'] ?? null;
        $personalityTraits = $data['personality_traits'] ?? null;
        unset($data['interests'], $data['personality_traits']);

        // Update basic profile data
        $user->update($data);

        // Sync interests if provided
        if ($interests !== null) {
            // Get interest IDs by name
            $interestIds = Interest::whereIn('name', $interests)->pluck('id');
            $user->interests()->sync($interestIds);
        }

        // Sync personality traits if provided
        if ($personalityTraits !== null) {
            // Get personality trait IDs by name
            $traitIds = PersonalityTrait::whereIn('name', $personalityTraits)->pluck('id');
            $user->personalityTraits()->sync($traitIds);
        }

        $user->load(['interests', 'personalityTraits']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Update user avatar.
     */
    public function updateAvatar(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ]);

        $user = auth()->user();

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $avatarPath]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar updated successfully',
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Update user banner.
     */
    public function updateBanner(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $user = auth()->user();

        // Delete old banner if exists
        if ($user->banner) {
            Storage::disk('public')->delete($user->banner);
        }

        // Store new banner
        $bannerPath = $request->file('banner')->store('banners', 'public');
        $user->update(['banner' => $bannerPath]);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Delete user banner.
     */
    public function deleteBanner(): JsonResponse
    {
        $user = auth()->user();

        if ($user->banner) {
            Storage::disk('public')->delete($user->banner);
            $user->update(['banner' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully',
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Delete user avatar.
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar deleted successfully',
            'data' => [
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Change user password.
     */
    public function updatePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();
        $user->update(['password' => $request->password]);

        // Optionally revoke all other tokens except current
        // $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get all available interests.
     */
    public function interests(): JsonResponse
    {
        $interests = Interest::orderBy('name')->get(['id', 'name', 'icon']);

        return response()->json([
            'success' => true,
            'data' => [
                'interests' => $interests,
            ],
        ]);
    }

    /**
     * Get all available personality traits.
     */
    public function personalityTraits(): JsonResponse
    {
        $traits = PersonalityTrait::orderBy('name')->get(['id', 'name', 'icon']);

        return response()->json([
            'success' => true,
            'data' => [
                'personality_traits' => $traits,
            ],
        ]);
    }

    /**
     * Format user data for API response.
     *
     * @param  \App\Models\User  $user
     * @return array<string, mixed>
     */
    protected function formatUserData($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
            'banner' => $user->banner ? Storage::disk('public')->url($user->banner) : null,
            'role' => $user->role,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'gender' => $user->gender,
            'bio' => $user->bio,
            'favorite_color' => $user->favorite_color,
            'favorite_music_genre' => $user->favorite_music_genre,
            'interests' => $user->interests->map(fn ($interest) => [
                'id' => $interest->id,
                'name' => $interest->name,
                'icon' => $interest->icon,
            ]),
            'personality_traits' => $user->personalityTraits->map(fn ($trait) => [
                'id' => $trait->id,
                'name' => $trait->name,
                'icon' => $trait->icon,
            ]),
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'created_at' => $user->created_at,
        ];
    }
}
