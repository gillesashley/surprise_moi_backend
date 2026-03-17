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
        $type = $request->input('type');

        if ($type === 'order') {
            $query = $this->orderPaymentsQuery();
        } elseif ($type === 'vendor-onboarding') {
            $query = $this->vendorOnboardingPaymentsQuery();
        } else {
            $query = $this->unionQuery();
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'ilike', "%{$search}%")
                    ->orWhere('user_name', 'ilike', "%{$search}%")
                    ->orWhere('user_email', 'ilike', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Date range
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to').' 23:59:59');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['created_at', 'amount', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $payments = $query->paginate(15)->withQueryString();

        return Inertia::render('payments/index', [
            'payments' => $payments,
            'statuses' => self::STATUSES,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'type' => $type,
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
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

    private function orderPaymentsQuery()
    {
        return DB::table('payments')
            ->join('users', 'payments.user_id', '=', 'users.id')
            ->leftJoin('orders', 'payments.order_id', '=', 'orders.id')
            ->select([
                'payments.id',
                DB::raw("'order' as type"),
                'payments.reference',
                'users.name as user_name',
                'users.email as user_email',
                'payments.amount',
                'payments.currency',
                'payments.status',
                'payments.channel',
                'payments.paid_at',
                'payments.created_at',
                'orders.order_number as related_reference',
            ]);
    }

    private function vendorOnboardingPaymentsQuery()
    {
        return DB::table('vendor_onboarding_payments')
            ->join('users', 'vendor_onboarding_payments.user_id', '=', 'users.id')
            ->select([
                'vendor_onboarding_payments.id',
                DB::raw("'vendor_onboarding' as type"),
                'vendor_onboarding_payments.reference',
                'users.name as user_name',
                'users.email as user_email',
                'vendor_onboarding_payments.amount',
                'vendor_onboarding_payments.currency',
                'vendor_onboarding_payments.status',
                'vendor_onboarding_payments.channel',
                'vendor_onboarding_payments.paid_at',
                'vendor_onboarding_payments.created_at',
                DB::raw("NULL as related_reference"),
            ]);
    }

    private function unionQuery()
    {
        $orderQuery = $this->orderPaymentsQuery();
        $vendorQuery = $this->vendorOnboardingPaymentsQuery();

        return DB::query()->fromSub(
            $orderQuery->unionAll($vendorQuery),
            'payments_union'
        );
    }
}
