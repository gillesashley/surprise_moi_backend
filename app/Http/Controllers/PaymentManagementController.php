<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncPaymentRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Payment;
use App\Models\VendorOnboardingPayment;
use App\Services\PaystackService;
use App\Services\VendorOnboardingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentManagementController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'success', 'failed', 'abandoned', 'reversed', 'cancelled'];

    public function index(Request $request): Response
    {
        // TODO: Task 2
        return Inertia::render('payments/index', []);
    }

    public function show(string $type, int $id): Response
    {
        // TODO: Task 3
        return Inertia::render('payments/show', []);
    }

    public function verify(VerifyPaymentRequest $request, string $type, int $id): JsonResponse
    {
        // TODO: Task 4
        return response()->json([]);
    }

    public function sync(SyncPaymentRequest $request, string $type, int $id): JsonResponse
    {
        // TODO: Task 4
        return response()->json([]);
    }
}
