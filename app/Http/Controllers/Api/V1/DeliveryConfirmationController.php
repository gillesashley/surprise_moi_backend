<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeliveryConfirmationController extends Controller
{
    /**
     * Confirm delivery using PIN and order number.
     * This endpoint is designed to be simple for delivery personnel.
     *
     * Required fields:
     * - delivery_pin: 4-digit PIN provided to customer
     * - order_number: Order number (e.g., VND-WAPZ-XXXX-XXXXXX-XX)
     * - delivery_person_name (optional): Name of person confirming delivery
     */
    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivery_pin' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'order_number' => ['required', 'string', 'max:50'],
            'delivery_person_name' => ['nullable', 'string', 'max:100'],
        ], [
            'delivery_pin.required' => 'Please enter the 4-digit PIN.',
            'delivery_pin.size' => 'PIN must be exactly 4 digits.',
            'delivery_pin.regex' => 'PIN must contain only numbers.',
            'order_number.required' => 'Please enter the order number.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input. Please check your entries.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pin = $request->input('delivery_pin');
        $orderNumber = $request->input('order_number');
        $deliveryPersonName = $request->input('delivery_person_name', 'Delivery Person');

        // Find order with matching PIN and order number
        $order = Order::where('delivery_pin', $pin)
            ->where('order_number', $orderNumber)
            ->whereNull('delivery_confirmed_at')
            ->first();

        if (! $order) {
            Log::warning('Delivery confirmation failed', [
                'pin' => $pin,
                'order_number' => $orderNumber,
                'reason' => 'Order not found or already confirmed',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN or order number, or delivery already confirmed.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update order with delivery confirmation
            $order->update([
                'delivery_confirmed_at' => now(),
                'delivery_confirmed_by' => $deliveryPersonName,
                'delivered_at' => now(),
                'status' => 'delivered',
            ]);

            // Move vendor balance from pending to available
            if ($order->vendor_id && $order->vendor_payout_amount) {
                $vendorBalance = $order->vendor->vendorBalance()->firstOrCreate([
                    'vendor_id' => $order->vendor_id,
                ], [
                    'currency' => $order->currency,
                ]);

                // Move from pending to available balance
                $vendorBalance->pending_balance -= $order->vendor_payout_amount;
                $vendorBalance->available_balance += $order->vendor_payout_amount;
                $vendorBalance->save();

                // Log transaction
                $order->vendor->vendorTransactions()->create([
                    'type' => 'credit_sale',
                    'order_id' => $order->id,
                    'amount' => $order->vendor_payout_amount,
                    'currency' => $order->currency,
                    'status' => 'completed',
                    'description' => "Order {$order->order_number} delivery confirmed",
                ]);
            }

            DB::commit();

            Log::info('Delivery confirmed successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'confirmed_by' => $deliveryPersonName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery confirmed successfully! The vendor has been notified.',
                'order' => [
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'confirmed_at' => $order->delivery_confirmed_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Delivery confirmation failed', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm delivery. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify if a PIN and order number combination is valid (without confirming).
     * Useful for pre-validation before actual confirmation.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivery_pin' => ['required', 'string', 'size:4'],
            'order_number' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Invalid input.',
            ], 422);
        }

        $order = Order::where('delivery_pin', $request->input('delivery_pin'))
            ->where('order_number', $request->input('order_number'))
            ->whereNull('delivery_confirmed_at')
            ->first();

        if (! $order) {
            return response()->json([
                'success' => true,
                'valid' => false,
                'message' => 'PIN/Order combination not found or already confirmed.',
            ]);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'message' => 'Valid. Ready for confirmation.',
            'order' => [
                'order_number' => $order->order_number,
                'total' => $order->total,
                'currency' => $order->currency,
                'vendor' => $order->vendor->name ?? 'Unknown',
            ],
        ]);
    }
}
