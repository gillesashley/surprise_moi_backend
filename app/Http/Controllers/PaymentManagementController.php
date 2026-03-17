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
        $payment = $this->findPayment($type, $id);

        return Inertia::render('payments/show', [
            'payment' => $payment,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findPayment(string $type, int $id): array
    {
        if ($type === 'order') {
            $payment = Payment::with(['user:id,name,email,phone', 'order:id,order_number,status'])
                ->findOrFail($id);

            return [
                'id' => $payment->id,
                'type' => 'order',
                'reference' => $payment->reference,
                'paystack_reference' => $payment->paystack_reference,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'channel' => $payment->channel,
                'payment_method_type' => $payment->payment_method_type,
                'card_last4' => $payment->card_last4,
                'card_type' => $payment->card_type,
                'card_exp_month' => $payment->card_exp_month,
                'card_exp_year' => $payment->card_exp_year,
                'card_bank' => $payment->card_bank,
                'mobile_money_number' => $payment->mobile_money_number,
                'mobile_money_provider' => $payment->mobile_money_provider,
                'gateway_response' => $payment->gateway_response,
                'failure_reason' => $payment->failure_reason,
                'ip_address' => $payment->ip_address,
                'metadata' => $payment->metadata,
                'paid_at' => $payment->paid_at,
                'verified_at' => $payment->verified_at,
                'created_at' => $payment->created_at,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email,
                    'phone' => $payment->user->phone,
                ] : null,
                'related' => $payment->order ? [
                    'order_number' => $payment->order->order_number,
                    'order_status' => $payment->order->status,
                    'order_id' => $payment->order->id,
                ] : null,
            ];
        }

        $payment = VendorOnboardingPayment::with([
            'user:id,name,email,phone',
            'vendorApplication:id,status,completed_step,current_step',
        ])->findOrFail($id);

        return [
            'id' => $payment->id,
            'type' => 'vendor_onboarding',
            'reference' => $payment->reference,
            'paystack_reference' => $payment->paystack_reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'channel' => $payment->channel,
            'payment_method_type' => $payment->payment_method_type,
            'card_last4' => $payment->card_last4,
            'card_type' => $payment->card_type,
            'card_exp_month' => $payment->card_exp_month,
            'card_exp_year' => $payment->card_exp_year,
            'card_bank' => $payment->card_bank,
            'mobile_money_number' => $payment->mobile_money_number,
            'mobile_money_provider' => $payment->mobile_money_provider,
            'gateway_response' => $payment->gateway_response,
            'failure_reason' => $payment->failure_reason,
            'ip_address' => $payment->ip_address,
            'metadata' => $payment->metadata,
            'paid_at' => $payment->paid_at,
            'verified_at' => $payment->verified_at,
            'created_at' => $payment->created_at,
            'user' => $payment->user ? [
                'id' => $payment->user->id,
                'name' => $payment->user->name,
                'email' => $payment->user->email,
                'phone' => $payment->user->phone,
            ] : null,
            'related' => $payment->vendorApplication ? [
                'application_id' => $payment->vendorApplication->id,
                'application_status' => $payment->vendorApplication->status,
                'current_step' => $payment->vendorApplication->current_step,
                'completed_step' => $payment->vendorApplication->completed_step,
            ] : null,
        ];
    }

    public function verify(VerifyPaymentRequest $request, string $type, int $id): JsonResponse
    {
        $payment = $type === 'order'
            ? Payment::findOrFail($id)
            : VendorOnboardingPayment::findOrFail($id);

        $baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
        $secretKey = config('services.paystack.secret_key');

        try {
            $response = Http::withToken($secretKey)
                ->timeout(30)
                ->get("{$baseUrl}/transaction/verify/{$payment->reference}");

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment with Paystack.',
                    'error' => $response->json('message') ?? 'Unknown error',
                ], 422);
            }

            $paystackData = $response->json();

            return response()->json([
                'success' => true,
                'local_status' => $payment->status,
                'paystack_data' => $paystackData,
                'status_mismatch' => ($paystackData['data']['status'] ?? '') !== $payment->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Paystack verification failed from admin', [
                'payment_type' => $type,
                'payment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not reach Paystack. Try again later.',
            ], 422);
        }
    }

    public function sync(SyncPaymentRequest $request, string $type, int $id): JsonResponse
    {
        if ($type === 'order') {
            $payment = Payment::findOrFail($id);
            $beforeStatus = $payment->status;

            $paystackService = app(PaystackService::class);
            $result = $paystackService->verifyTransaction($payment->reference);
        } else {
            $payment = VendorOnboardingPayment::findOrFail($id);
            $beforeStatus = $payment->status;

            $paymentService = app(VendorOnboardingPaymentService::class);
            $result = $paymentService->verifyPayment($payment);
        }

        Log::info('Admin payment sync performed', [
            'admin_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'payment_type' => $type,
            'payment_id' => $id,
            'reference' => $payment->reference,
            'status_before' => $beforeStatus,
            'status_after' => $payment->fresh()->status,
            'result' => $result['success'],
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'payment' => $this->findPayment($type, $id),
        ]);
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
