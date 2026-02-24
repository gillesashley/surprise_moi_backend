<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\DeleteAccountRequest;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    /**
     * Delete the authenticated user's account.
     *
     * Verifies the user's password, revokes all Sanctum tokens,
     * and permanently deletes the account.
     */
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        // Revoke all personal access tokens before deletion
        $user->tokens()->delete();

        // Permanently delete the user account
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}
