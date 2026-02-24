<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreOrderRequest - Validates order creation data.
 *
 * Validates:
 * - At least one item (product or service)
 * - Each item has valid orderable_type, orderable_id, and quantity
 * - Optional variant selection for products
 * - Optional coupon code (must exist in database)
 * - Required delivery address (must belong to user)
 * - Optional special instructions and occasion
 * - Optional scheduled delivery (must be in future)
 *
 * Used by: OrderController@store
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if user is authorized to make this request.
     * Order creation allowed for all authenticated users.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for order creation.
     *
     * Rules:
     * - idempotency_key: Optional unique key for preventing duplicate orders
     * - items: Array of products/services to purchase
     * - coupon_code: Optional discount code
     * - delivery_address_id: Where to deliver (must belong to user)
     * - special_instructions: Optional notes for vendor
     * - occasion: Why this order? (for gift recommendations)
     * - scheduled_datetime: Optional future delivery date
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:100'],          // Unique key for duplicate prevention
            'items' => ['required', 'array', 'min:1'],
            'items.*.orderable_type' => ['required', 'in:product,service'],  // What type of item
            'items.*.orderable_id' => ['required', 'integer', 'min:1'],      // ID of product/service
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],  // Specific variant
            'items.*.quantity' => ['required', 'integer', 'min:1'],          // How many
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],  // Discount code
            'delivery_address_id' => ['required', 'integer', 'exists:user_addresses,id'],
            'special_instructions' => ['nullable', 'string', 'max:500'],     // Notes for vendor
            'occasion' => ['nullable', 'in:birthday,anniversary,random_surprise,graduation,wedding,engagement,baby_shower,valentines_day,mothers_day,fathers_day,christmas,new_year,get_well_soon,congratulations,apology,thank_you,other'],
            'scheduled_datetime' => ['nullable', 'date', 'after:now'],       // Future delivery
        ];
    }

    /**
     * Get custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required to create an order.',
            'items.min' => 'At least one item is required to create an order.',
            'delivery_address_id.required' => 'Please select a delivery address.',
            'scheduled_datetime.after' => 'The scheduled delivery time must be in the future.',
        ];
    }
}
