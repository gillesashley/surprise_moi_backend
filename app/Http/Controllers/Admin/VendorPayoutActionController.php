<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\V1\AdminPayoutController;
use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web (session-authenticated) counterpart to AdminPayoutController.
 *
 * The admin dashboard is a first-party Inertia SPA authenticated via session
 * cookies, while AdminPayoutController lives under auth:sanctum with Bearer
 * token auth. This controller delegates to the same business logic and
 * translates JSON responses into Inertia-friendly redirects with flash.
 */
class VendorPayoutActionController extends Controller
{
    public function __construct(private readonly AdminPayoutController $api) {}

    public function approve(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        return $this->toFlash($this->api->approve($request, $payoutRequest));
    }

    public function reject(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        return $this->toFlash($this->api->reject($request, $payoutRequest));
    }

    public function markAsPaid(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        return $this->toFlash($this->api->markAsPaid($request, $payoutRequest));
    }

    private function toFlash(JsonResponse $response): RedirectResponse
    {
        /** @var array{success?: bool, message?: string, errors?: array<string, array<int, string>>} $data */
        $data = $response->getData(true);
        $status = $response->getStatusCode();

        if ($status === 422 && isset($data['errors'])) {
            return back()->withErrors($data['errors'])->withInput();
        }

        if ($status >= 400 || ($data['success'] ?? true) === false) {
            return back()->with('error', $data['message'] ?? 'Action failed.');
        }

        return back()->with('success', $data['message'] ?? 'Action completed.');
    }
}
