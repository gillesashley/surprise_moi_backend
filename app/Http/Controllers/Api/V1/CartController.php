<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddToCartRequest;
use App\Http\Requests\Api\V1\MergeCartRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cartToken = $request->header('X-Cart-Token');

        $cart = $this->cartService->getOrCreateCart($user, $cartToken);
        $cart->load('items.product');

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $this->formatCart($cart),
                'cart_token' => $cart->cart_token,
            ],
        ]);
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cartToken = $request->header('X-Cart-Token');

            $cart = $this->cartService->getOrCreateCart($user, $cartToken);

            $cartItem = $this->cartService->addItem($cart, $request->validated());

            $cart->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart',
                'data' => [
                    'cart' => $this->formatCart($cart),
                    'cart_token' => $cart->cart_token,
                    'item' => $this->formatCartItem($cartItem),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'add_item_failed',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        }
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        try {
            $user = $request->user();
            $cartToken = $request->header('X-Cart-Token');

            if ($user && $cartItem->cart->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'This cart item does not belong to you',
                    ],
                ], 403);
            }

            if (! $user && $cartItem->cart->cart_token !== $cartToken) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'This cart item does not belong to you',
                    ],
                ], 403);
            }

            $cartItem = $this->cartService->updateItem($cartItem, $request->validated());
            $cart = $cartItem->cart->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated',
                'data' => [
                    'cart' => $this->formatCart($cart),
                    'item' => $this->formatCartItem($cartItem),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'update_item_failed',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        }
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        try {
            $user = $request->user();
            $cartToken = $request->header('X-Cart-Token');

            if ($user && $cartItem->cart->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'This cart item does not belong to you',
                    ],
                ], 403);
            }

            if (! $user && $cartItem->cart->cart_token !== $cartToken) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'This cart item does not belong to you',
                    ],
                ], 403);
            }

            $cart = $cartItem->cart;
            $this->cartService->removeItem($cartItem);
            $cart->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart',
                'data' => [
                    'cart' => $this->formatCart($cart),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'remove_item_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function clear(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cartToken = $request->header('X-Cart-Token');

            $cart = $this->cartService->getOrCreateCart($user, $cartToken);
            $cart = $this->cartService->clearCart($cart);

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared',
                'data' => [
                    'cart' => $this->formatCart($cart),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'clear_cart_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function merge(MergeCartRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'You must be logged in to merge carts',
                    ],
                ], 401);
            }

            $guestCartToken = $request->input('guest_cart_token');
            $guestCart = Cart::where('cart_token', $guestCartToken)->firstOrFail();

            $userCart = $this->cartService->getOrCreateCart($user);

            $mergedCart = $this->cartService->mergeCarts($userCart, $guestCart);

            return response()->json([
                'success' => true,
                'message' => 'Carts merged successfully',
                'data' => [
                    'cart' => $this->formatCart($mergedCart),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'merge_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    protected function formatCart(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'cart_token' => $cart->cart_token,
            'currency' => $cart->currency,
            'items' => $cart->items->map(fn ($item) => $this->formatCartItem($item)),
            'subtotal_cents' => $cart->subtotal_cents,
            'shipping_cents' => $cart->shipping_cents,
            'tax_cents' => $cart->tax_cents,
            'discount_cents' => $cart->discount_cents,
            'total_cents' => $cart->total_cents,
            'subtotal' => $cart->subtotal,
            'shipping' => $cart->shipping,
            'tax' => $cart->tax,
            'discount' => $cart->discount,
            'total' => $cart->total,
            'version' => $cart->version,
            'items_count' => $cart->items->count(),
        ];
    }

    protected function formatCartItem(CartItem $cartItem): array
    {
        return [
            'id' => $cartItem->id,
            'product_id' => $cartItem->product_id,
            'vendor_id' => $cartItem->vendor_id,
            'sku' => $cartItem->sku,
            'name' => $cartItem->name,
            'unit_price_cents' => $cartItem->unit_price_cents,
            'quantity' => $cartItem->quantity,
            'line_total_cents' => $cartItem->line_total_cents,
            'unit_price' => $cartItem->unit_price,
            'line_total' => $cartItem->line_total,
            'metadata' => $cartItem->metadata,
            'product' => $cartItem->product ? [
                'id' => $cartItem->product->id,
                'name' => $cartItem->product->name,
                'thumbnail' => $cartItem->product->thumbnail ? Storage::disk('public')->url($cartItem->product->thumbnail) : null,
                'stock' => $cartItem->product->stock,
                'is_available' => $cartItem->product->is_available,
            ] : null,
        ];
    }
}
