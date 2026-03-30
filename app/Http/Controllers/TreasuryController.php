<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeTransferRequest;
use App\Http\Requests\InitiateTransferRequest;
use App\Http\Requests\ResendTransferOtpRequest;
use App\Http\Requests\SaveCompanyBankAccountRequest;
use App\Http\Requests\VerifyBankAccountRequest;
use App\Models\CompanyBankAccount;
use App\Models\TreasuryTransfer;
use App\Services\CacheService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TreasuryController extends Controller
{
    public function __construct(protected PaystackService $paystackService) {}

    /**
     * Ensure only super_admin can access treasury.
     */
    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()->role === 'super_admin', 403);
    }

    /**
     * Overview tab — balance, transaction totals, recent transactions.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $balance = $this->paystackService->checkBalance();
        $totals = $this->paystackService->getTransactionTotals();
        $recentTransactions = $this->paystackService->listTransactions(['perPage' => 10, 'page' => 1]);

        return Inertia::render('treasury/index', [
            'tab' => 'overview',
            'balance' => $balance,
            'totals' => $totals,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Transactions tab — full transaction list with filters.
     */
    public function transactions(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $filters = $request->only(['from', 'to', 'status', 'page']);
        $filters['perPage'] = 20;
        $transactions = $this->paystackService->listTransactions($filters);

        return Inertia::render('treasury/index', [
            'tab' => 'transactions',
            'transactions' => $transactions,
            'filters' => $request->only(['from', 'to', 'status']),
        ]);
    }

    /**
     * Settlements tab — settlement history.
     */
    public function settlements(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $filters = $request->only(['from', 'to', 'page']);
        $filters['perPage'] = 20;
        $settlements = $this->paystackService->listSettlements($filters);

        return Inertia::render('treasury/index', [
            'tab' => 'settlements',
            'settlements' => $settlements,
            'filters' => $request->only(['from', 'to']),
        ]);
    }

    /**
     * Transfers tab — transfer history + bank account + transfer form.
     */
    public function transfers(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $balance = $this->paystackService->checkBalance();
        $bankAccount = CompanyBankAccount::getActive();
        $transferHistory = TreasuryTransfer::with('companyBankAccount', 'initiatedBy')
            ->latest()
            ->paginate(20);

        $banks = $this->paystackService->getBanks('ghana');

        return Inertia::render('treasury/index', [
            'tab' => 'transfers',
            'balance' => $balance,
            'bankAccount' => $bankAccount,
            'transferHistory' => $transferHistory,
            'banks' => $banks,
        ]);
    }

    /**
     * Bust cache for all treasury data.
     */
    public function refresh(): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        CacheService::flushTreasuryCaches();

        return back()->with('success', 'Treasury data refreshed.');
    }

    /**
     * Get the current company bank account.
     */
    public function bankAccount(): Response
    {
        $this->authorizeSuperAdmin();

        $bankAccount = CompanyBankAccount::getActive();
        $banks = $this->paystackService->getBanks('ghana');

        return Inertia::render('treasury/index', [
            'tab' => 'transfers',
            'bankAccount' => $bankAccount,
            'banks' => $banks,
            'showBankForm' => true,
        ]);
    }

    /**
     * Verify a bank account via Paystack.
     */
    public function verifyBankAccount(VerifyBankAccountRequest $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $result = $this->paystackService->resolveAccountNumber(
            $request->input('account_number'),
            $request->input('bank_code')
        );

        return response()->json($result);
    }

    /**
     * Save a company bank account (after Paystack verification).
     */
    public function saveBankAccount(SaveCompanyBankAccountRequest $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        // Create transfer recipient on Paystack
        $recipientResult = $this->paystackService->createTransferRecipient(
            'ghipss',
            $request->input('account_name'),
            $request->input('account_number'),
            $request->input('bank_code')
        );

        if (! $recipientResult['success']) {
            return back()->withErrors(['account_number' => $recipientResult['message'] ?? 'Failed to create transfer recipient on Paystack.']);
        }

        $recipientCode = $recipientResult['data']['recipient_code'] ?? null;

        $account = CompanyBankAccount::create([
            'account_name' => $request->input('account_name'),
            'account_number' => $request->input('account_number'),
            'bank_code' => $request->input('bank_code'),
            'bank_name' => $request->input('bank_name'),
            'paystack_recipient_code' => $recipientCode,
            'added_by' => auth()->id(),
        ]);

        $account->activate();

        return redirect()->route('treasury.transfers')
            ->with('success', 'Bank account saved and set as active.');
    }

    /**
     * Initiate a transfer to the active company bank account.
     */
    public function initiateTransfer(InitiateTransferRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $bankAccount = CompanyBankAccount::getActive();
        if (! $bankAccount || ! $bankAccount->paystack_recipient_code) {
            return back()->withErrors(['amount' => 'No active bank account configured. Please set up a bank account first.']);
        }

        $amountGhs = (float) $request->input('amount');
        $amountPesewas = (int) round($amountGhs * 100);

        // Check balance before calling Paystack
        $balanceData = $this->paystackService->checkBalance();
        if (! $balanceData['success']) {
            return back()->withErrors(['amount' => 'Unable to verify Paystack balance. Please try again.']);
        }

        $availableBalance = $balanceData['data'][0]['balance'] ?? 0;
        if ($amountPesewas > $availableBalance) {
            $availableGhs = number_format($availableBalance / 100, 2);

            return back()->withErrors(['amount' => "Insufficient Paystack balance. Available: GHS {$availableGhs}"]);
        }

        // Create local audit record
        $transfer = TreasuryTransfer::create([
            'company_bank_account_id' => $bankAccount->id,
            'initiated_by' => auth()->id(),
            'amount' => $amountGhs,
            'amount_in_pesewas' => $amountPesewas,
            'status' => TreasuryTransfer::STATUS_PENDING,
        ]);

        // Call Paystack
        $result = $this->paystackService->initiateTransfer(
            $amountPesewas,
            $bankAccount->paystack_recipient_code,
            "Treasury transfer {$transfer->paystack_reference}",
            $transfer->paystack_reference
        );

        if (! $result['success']) {
            $transfer->update([
                'status' => TreasuryTransfer::STATUS_FAILED,
                'paystack_response' => $result,
            ]);

            return back()->withErrors(['amount' => $result['message'] ?? 'Transfer initiation failed.']);
        }

        $transferCode = $result['data']['transfer_code'] ?? '';

        $transfer->update([
            'paystack_transfer_code' => $transferCode,
            'status' => TreasuryTransfer::STATUS_OTP_REQUIRED,
            'paystack_response' => $result,
        ]);

        return response()->json([
            'success' => true,
            'transfer_code' => $transferCode,
            'message' => 'OTP sent. Please enter it to complete the transfer.',
        ]);
    }

    /**
     * Finalize a transfer with OTP.
     */
    public function finalizeTransfer(FinalizeTransferRequest $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $transferCode = $request->input('transfer_code');
        $otp = $request->input('otp');

        $transfer = TreasuryTransfer::where('paystack_transfer_code', $transferCode)->first();
        if (! $transfer) {
            return response()->json(['success' => false, 'message' => 'Transfer not found.'], 404);
        }

        $result = $this->paystackService->finalizeTransfer($transferCode, $otp);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to finalize transfer.',
            ], 422);
        }

        $transfer->update([
            'status' => TreasuryTransfer::STATUS_PROCESSING,
            'paystack_response' => $result,
        ]);

        CacheService::flushTreasuryTransferCaches();

        return response()->json([
            'success' => true,
            'message' => 'Transfer is being processed.',
        ]);
    }

    /**
     * Resend OTP for a pending transfer.
     */
    public function resendTransferOtp(ResendTransferOtpRequest $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $result = $this->paystackService->resendTransferOtp($request->input('transfer_code'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
