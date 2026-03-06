<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PriceChangedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Services\CartService;
use App\Services\VendorBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        protected VendorBalanceService $vendorBalanceService,
        protected CartService $cartService,
    ) {}

    /**
     * Get a paginated list of orders for the authenticated user.
     *
     * Vendors see their vendor orders, customers see their purchase orders, admins see all orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->with(['items.orderable', 'deliveryAddress', 'coupon', 'vendor']);

        if ($request->user()->role === 'vendor') {
            $query->where('vendor_id', $request->user()->id);
        } elseif ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%'.$request->input('search').'%')
                    ->orWhere('tracking_number', 'like', '%'.$request->input('search').'%');
            });
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * Create a new order with products/services from a single vendor.
     *
     * Validates items, applies coupons, calculates totals, and creates order with items.
     * Supports idempotency via optional idempotency_key parameter to prevent duplicate orders.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $reservedStock = [];

        try {
            DB::beginTransaction();

            // Check for existing order with same idempotency key
            if ($request->filled('idempotency_key')) {
                $existingOrder = Order::where('user_id', $request->user()->id)
                    ->where('idempotency_key', $request->input('idempotency_key'))
                    ->first();

                if ($existingOrder) {
                    DB::commit();

                    return response()->json([
                        'message' => 'Order already exists (idempotent request).',
                        'order' => new OrderResource($existingOrder->load(['items.orderable', 'deliveryAddress', 'coupon', 'vendor'])),
                    ], 200);
                }
            }

            $cartPriceMap = $this->cartService->getCartPriceMap($request->user());

            $items = $request->input('items');
            $subtotal = 0;
            $vendorId = null;

            $processedItems = [];
            foreach ($items as $item) {
                $orderable = $this->getOrderable($item['orderable_type'], $item['orderable_id']);

                if (! $orderable) {
                    throw new \Exception('Item not found: '.$item['orderable_type'].' #'.$item['orderable_id']);
                }

                if ($orderable instanceof Product) {
                    if (! $orderable->is_available) {
                        throw new \Exception('Product "'.$orderable->name.'" is not available.');
                    }

                    // Atomic stock reservation: decrement only if sufficient stock exists
                    if ($orderable->stock !== null) {
                        $affected = Product::where('id', $orderable->id)
                            ->where('stock', '>=', $item['quantity'])
                            ->update(['stock' => DB::raw('stock - '.(int) $item['quantity'])]);

                        if ($affected === 0) {
                            throw new \Exception('Insufficient stock for "'.$orderable->name.'".');
                        }

                        $reservedStock[] = ['id' => $orderable->id, 'quantity' => $item['quantity']];
                    }
                }

                if ($orderable instanceof Service && $orderable->availability !== 'available') {
                    throw new \Exception('Service "'.$orderable->name.'" is not available for booking.');
                }

                // For products: use cart price if available, detect staleness
                if ($orderable instanceof Product && isset($cartPriceMap[$orderable->id])) {
                    $cartPriceCents = $cartPriceMap[$orderable->id];
                    $currentPrice = $orderable->effective_price;
                    $currentPriceCents = (int) round($currentPrice * 100);

                    // Reject if price has changed in either direction — user must see current prices
                    if ($cartPriceCents !== $currentPriceCents) {
                        throw new PriceChangedException(
                            $orderable->name,
                            $cartPriceCents / 100,
                            $currentPrice
                        );
                    }

                    $unitPrice = $cartPriceCents / 100;
                } else {
                    // Fallback for services or items not in cart
                    if ($orderable instanceof Product) {
                        $unitPrice = $orderable->effective_price;
                    } else {
                        $unitPrice = $orderable->discount_price ?? $orderable->price ?? $orderable->charge_start;
                    }
                }

                $itemSubtotal = $unitPrice * $item['quantity'];
                $subtotal += $itemSubtotal;

                if ($orderable->vendor_id) {
                    if ($vendorId && $vendorId !== $orderable->vendor_id) {
                        throw new \Exception('Cannot order items from multiple vendors in a single order.');
                    }
                    $vendorId = $orderable->vendor_id;
                }

                $processedItems[] = [
                    'orderable' => $orderable,
                    'orderable_type' => $item['orderable_type'] === 'product' ? Product::class : Service::class,
                    'orderable_id' => $orderable->id,
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $discountAmount = 0;
            $coupon = null;

            if ($request->filled('coupon_code')) {
                $coupon = Coupon::where('code', $request->input('coupon_code'))->first();

                if (! $coupon || ! $coupon->isValid()) {
                    throw new \Exception('Invalid or expired coupon code.');
                }

                if (! $coupon->canBeUsedBy($request->user())) {
                    throw new \Exception('You have reached the usage limit for this coupon.');
                }

                $discountAmount = $coupon->calculateDiscount($subtotal);

                if ($discountAmount === 0.0 && $coupon->min_purchase_amount) {
                    throw new \Exception('Minimum purchase amount of '.$coupon->currency.' '.$coupon->min_purchase_amount.' required for this coupon.');
                }
            }

            $deliveryFee = 0;
            if (isset($processedItems[0]['orderable']->delivery_fee)) {
                $deliveryFee = $processedItems[0]['orderable']->delivery_fee ?? 0;
            }

            $total = $subtotal - $discountAmount + $deliveryFee;

            // Retry logic for handling duplicate order_number (race condition)
            $maxAttempts = 3;
            $attempt = 0;
            $order = null;

            while ($attempt < $maxAttempts && ! $order) {
                try {
                    $order = Order::create([
                        'user_id' => $request->user()->id,
                        'vendor_id' => $vendorId,
                        'idempotency_key' => $request->input('idempotency_key'),
                        'subtotal' => $subtotal,
                        'discount_amount' => $discountAmount,
                        'coupon_id' => $coupon?->id,
                        'delivery_fee' => $deliveryFee,
                        'total' => $total,
                        'currency' => config('app.currency', 'USD'),
                        'status' => 'pending',
                        'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                        'delivery_address_id' => $request->input('delivery_address_id'),
                        'special_instructions' => $request->input('special_instructions'),
                        'scheduled_datetime' => $request->input('scheduled_datetime'),
                    ]);

                    // Order created successfully, break the loop
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    // Check if it's a duplicate key violation on order_number
                    if (str_contains($e->getMessage(), 'orders_order_number_unique')) {
                        $attempt++;
                        if ($attempt >= $maxAttempts) {
                            throw new \Exception('Failed to generate unique order number after '.$maxAttempts.' attempts. Please try again.');
                        }
                        // Wait a tiny bit before retrying (to avoid hammering the DB)
                        usleep(50000); // 50ms

                        // The next iteration will generate a new order_number automatically
                        continue;
                    }

                    // If it's not a duplicate key error, re-throw
                    throw $e;
                }
            }

            if (! $order) {
                throw new \Exception('Failed to create order after multiple attempts.');
            }

            foreach ($processedItems as $processedItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'orderable_type' => $processedItem['orderable_type'],
                    'orderable_id' => $processedItem['orderable_id'],
                    'variant_id' => $processedItem['variant_id'],
                    'quantity' => $processedItem['quantity'],
                    'unit_price' => $processedItem['unit_price'],
                    'subtotal' => $processedItem['subtotal'],
                    'snapshot' => $processedItem['orderable']->toArray(),
                ]);
            }

            if ($coupon) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $request->user()->id,
                    'order_id' => $order->id,
                    'discount_amount' => $discountAmount,
                    'used_at' => now(),
                ]);

                $coupon->increment('used_count');
            }

            DB::commit();

            // Clear the user's cart after successful order
            try {
                $userCart = Cart::where('user_id', $request->user()->id)->first();
                if ($userCart) {
                    $this->cartService->clearCart($userCart);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to clear cart after order creation', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Order created successfully.',
                'order' => new OrderResource($order->load(['items.orderable', 'deliveryAddress', 'coupon', 'vendor'])),
            ], 201);
        } catch (PriceChangedException $e) {
            foreach ($reservedStock as $reserved) {
                Product::where('id', $reserved['id'])->increment('stock', $reserved['quantity']);
            }
            DB::rollBack();

            return response()->json([
                'code' => 'price_changed',
                'message' => $e->getMessage(),
                'product' => $e->productName,
                'cart_price' => $e->cartPrice,
                'current_price' => $e->currentPrice,
            ], 409);
        } catch (\Exception $e) {
            // Restore reserved stock before rolling back
            foreach ($reservedStock as $reserved) {
                Product::where('id', $reserved['id'])->increment('stock', $reserved['quantity']);
            }

            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create order: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get details of a specific order by ID.
     *
     * Users can only view their own orders unless they are admin or the vendor.
     */
    public function show(Order $order): JsonResponse
    {
        if (
            $order->user_id !== request()->user()->id &&
            request()->user()->role !== 'admin' &&
            ($order->vendor_id !== request()->user()->id || request()->user()->role !== 'vendor')
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'order' => new OrderResource($order->load(['items.orderable', 'deliveryAddress', 'coupon', 'vendor'])),
        ]);
    }

    /**
     * Update the status of an order.
     *
     * Only vendors can update their order status through various stages (processing, shipped, delivered, etc).
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $status = $request->input('status');

        if ($status === 'confirmed') {
            $order->markAsConfirmed();
        } elseif ($status === 'fulfilled') {
            $order->markAsFulfilled();

            // Release funds from pending to available balance
            try {
                if ($order->payment_status === 'paid') {
                    $this->vendorBalanceService->releaseFunds($order);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to release vendor funds for fulfilled order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($status === 'shipped') {
            $order->markAsShipped();

            // Release funds from pending to available balance
            try {
                if ($order->payment_status === 'paid') {
                    $this->vendorBalanceService->releaseFunds($order);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to release vendor funds for shipped order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($status === 'delivered') {
            $order->markAsDelivered();

            // Release funds from pending to available balance
            try {
                if ($order->payment_status === 'paid') {
                    $this->vendorBalanceService->releaseFunds($order);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to release vendor funds for delivered order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $order->update(['status' => $status]);
        }

        if ($request->filled('tracking_number')) {
            $order->update(['tracking_number' => $request->input('tracking_number')]);
        }

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => new OrderResource($order->fresh(['items.orderable', 'deliveryAddress', 'coupon', 'vendor'])),
        ]);
    }

    /**
     * Track an order's current status and location.
     *
     * Returns detailed tracking information including status history and delivery updates.
     */
    public function track(Order $order): JsonResponse
    {
        if ($order->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'tracking_number' => $order->tracking_number,
            'scheduled_datetime' => $order->scheduled_datetime,
            'confirmed_at' => $order->confirmed_at,
            'fulfilled_at' => $order->fulfilled_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'delivery_address' => $order->deliveryAddress,
        ]);
    }

    /**
     * Get vendor order statistics and summary data.
     *
     * Provides counts for orders by status, total revenue, and other key metrics. Vendors see their own stats, admins see all.
     */
    public function statistics(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'vendor' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Order::query();

        if ($request->user()->role === 'vendor') {
            $query->where('vendor_id', $request->user()->id);
        }

        $stats = [
            'total_orders' => (clone $query)->count(),
            'pending_orders' => (clone $query)->where('status', 'pending')->count(),
            'confirmed_orders' => (clone $query)->where('status', 'confirmed')->count(),
            'processing_orders' => (clone $query)->where('status', 'processing')->count(),
            'fulfilled_orders' => (clone $query)->where('status', 'fulfilled')->count(),
            'shipped_orders' => (clone $query)->where('status', 'shipped')->count(),
            'delivered_orders' => (clone $query)->where('status', 'delivered')->count(),
            'total_revenue' => (clone $query)->whereIn('status', ['fulfilled', 'shipped', 'delivered'])->sum('total'),
            'average_order_value' => (clone $query)->whereIn('status', ['fulfilled', 'shipped', 'delivered'])->avg('total'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    protected function getOrderable(string $type, int $id)
    {
        return match ($type) {
            'product' => Product::find($id),
            'service' => Service::find($id),
            default => null,
        };
    }
}
