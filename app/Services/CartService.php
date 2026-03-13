<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CartService - Manages shopping cart operations.
 *
 * This service handles:
 * - Guest carts (using UUID tokens)
 * - Authenticated user carts
 * - Cart merging when guest logs in
 * - Stock validation
 * - Price storage in cents (integer) for accuracy
 * - Automatic total recalculation
 */
class CartService
{
    /**
     * Get or create a cart for the given user or guest token.
     *
     * Logic:
     * - For authenticated users: Find or create cart by user_id
     * - For guests: Find cart by token, or create new cart with UUID token
     *
     * @param  User|null  $user  Authenticated user (if logged in)
     * @param  string|null  $cartToken  Guest cart token from cookie/header
     * @return Cart The cart instance
     */
    public function getOrCreateCart(?User $user = null, ?string $cartToken = null): Cart
    {
        // Authenticated user cart
        if ($user) {
            $cart = Cart::where('user_id', $user->id)->first();
            if (! $cart) {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'currency' => 'GHS',
                ]);
            }

            return $cart;
        }

        // Guest cart - try to find by token
        if ($cartToken) {
            $cart = Cart::where('cart_token', $cartToken)->first();
            if ($cart) {
                return $cart;
            }
        }

        // Create new guest cart with unique token
        return Cart::create([
            'cart_token' => Str::uuid(),
            'currency' => 'GHS',
        ]);
    }

    /**
     * Add a product to the cart.
     *
     * Features:
     * - Validates product availability and stock
     * - Stores price in cents (integer) for precision
     * - Merges with existing cart item if same product+SKU
     * - Increments version for cache invalidation
     * - Recalculates cart totals
     *
     * @param  Cart  $cart  The cart to add item to
     * @param  array  $data  Item data: product_id, quantity, unit_price_cents, sku, name, metadata
     * @return CartItem The created or updated cart item
     *
     * @throws \Exception If product unavailable or insufficient stock
     */
    public function addItem(Cart $cart, array $data): CartItem
    {
        return DB::transaction(function () use ($cart, $data) {
            $product = Product::findOrFail($data['product_id']);

            // Validate product availability
            if (! $product->is_available) {
                throw new \Exception('Product is not available');
            }

            // Validate stock availability
            if ($product->stock < $data['quantity']) {
                throw new \Exception("Requested quantity ({$data['quantity']}) exceeds available stock ({$product->stock})");
            }

            // Use provided price or product price (convert to cents)
            $unitPriceCents = isset($data['unit_price_cents'])
                ? $data['unit_price_cents']
                : (int) round($product->effective_price * 100);

            $sku = $data['sku'] ?? null;

            // Check if item already exists in cart (same product + SKU)
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('sku', $sku)
                ->first();

            if ($cartItem) {
                // Update existing item quantity
                $newQuantity = $cartItem->quantity + $data['quantity'];

                // Validate total quantity doesn't exceed stock
                if ($newQuantity > $product->stock) {
                    throw new \Exception("Total quantity ({$newQuantity}) exceeds available stock ({$product->stock})");
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    'unit_price_cents' => $unitPriceCents,  // Update price in case it changed
                ]);
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'vendor_id' => $product->vendor_id,
                    'sku' => $sku,
                    'name' => $data['name'] ?? $product->name,
                    'unit_price_cents' => $unitPriceCents,
                    'quantity' => $data['quantity'],
                    'metadata' => $data['metadata'] ?? null,  // For variant details, notes, etc.
                ]);
            }

            // Recalculate cart totals and bump version
            $cart->recalculateTotals();
            $cart->increment('version');
            $cart->save();

            return $cartItem->fresh();
        });
    }

    /**
     * Update a cart item's quantity, price, or metadata.
     *
     * @param  CartItem  $cartItem  The item to update
     * @param  array  $data  Fields to update: quantity, unit_price_cents, metadata
     * @return CartItem The updated cart item
     *
     * @throws \Exception If quantity invalid or exceeds stock
     */
    public function updateItem(CartItem $cartItem, array $data): CartItem
    {
        return DB::transaction(function () use ($cartItem, $data) {
            $product = $cartItem->product;

            if (isset($data['quantity'])) {
                if ($data['quantity'] < 1) {
                    throw new \Exception('Quantity must be at least 1');
                }

                if ($data['quantity'] > $product->stock) {
                    throw new \Exception("Requested quantity ({$data['quantity']}) exceeds available stock ({$product->stock})");
                }

                $cartItem->quantity = $data['quantity'];
            }

            if (isset($data['unit_price_cents'])) {
                $cartItem->unit_price_cents = $data['unit_price_cents'];
            }

            if (isset($data['metadata'])) {
                $cartItem->metadata = $data['metadata'];
            }

            $cartItem->save();

            // Recalculate cart totals
            $cart = $cartItem->cart;
            $cart->recalculateTotals();
            $cart->increment('version');
            $cart->save();

            return $cartItem->fresh();
        });
    }

    /**
     * Remove an item from the cart.
     * Also recalculates cart totals.
     *
     * @param  CartItem  $cartItem  The item to remove
     */
    public function removeItem(CartItem $cartItem): void
    {
        DB::transaction(function () use ($cartItem) {
            $cart = $cartItem->cart;
            $cartItem->delete();

            // Recalculate totals after removal
            $cart->recalculateTotals();
            $cart->increment('version');
            $cart->save();
        });
    }

    /**
     * Clear all items from a cart.
     * Useful for post-checkout cleanup.
     *
     * @param  Cart  $cart  The cart to clear
     * @return Cart The cleared cart
     */
    public function clearCart(Cart $cart): Cart
    {
        DB::transaction(function () use ($cart) {
            $cart->items()->delete();
            $cart->recalculateTotals();  // Resets totals to zero
            $cart->increment('version');
            $cart->save();
        });

        return $cart->fresh();
    }

    /**
     * Get a map of product prices from the user's cart.
     * Returns [product_id => unit_price_cents] for all items in the cart.
     */
    public function getCartPriceMap(User $user): array
    {
        $cart = Cart::where('user_id', $user->id)->first();

        if (! $cart) {
            return [];
        }

        return $cart->items()
            ->pluck('unit_price_cents', 'product_id')
            ->toArray();
    }

    /**
     * Merge a guest cart into a user's cart.
     * Called when a guest with items in cart logs in.
     *
     * Logic:
     * - If item exists in both carts, combine quantities (up to stock limit)
     * - If item only in guest cart, move it to user cart
     * - Delete guest cart after merge
     *
     * @param  Cart  $userCart  The authenticated user's cart
     * @param  Cart  $guestCart  The guest cart to merge from
     * @return Cart The merged user cart
     */
    public function mergeCarts(Cart $userCart, Cart $guestCart): Cart
    {
        // Eager load products to prevent N+1 queries during merge
        $guestCart->load('items.product');

        return DB::transaction(function () use ($userCart, $guestCart) {
            foreach ($guestCart->items as $guestItem) {
                // Check if user cart already has this product+SKU
                $existingItem = CartItem::where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->where('sku', $guestItem->sku)
                    ->first();

                if ($existingItem) {
                    // Merge quantities (respecting stock limit)
                    $product = $guestItem->product;
                    $newQuantity = $existingItem->quantity + $guestItem->quantity;

                    // Cap at available stock
                    $newQuantity = min($newQuantity, $product->stock);

                    $existingItem->update([
                        'quantity' => $newQuantity,
                    ]);
                } else {
                    // Move item to user cart
                    $guestItem->update([
                        'cart_id' => $userCart->id,
                    ]);
                }
            }

            // Delete guest cart
            $guestCart->delete();

            // Recalculate totals for merged cart
            $userCart->recalculateTotals();
            $userCart->increment('version');
            $userCart->save();

            return $userCart->fresh(['items']);
        });
    }
}
