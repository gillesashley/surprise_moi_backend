<?php

namespace App\Http\Requests\Api;

use App\Models\Order;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the validation rules
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    $order = Order::find($value);

                    // Check if order exists
                    if (! $order) {
                        $fail('The order does not exist.');

                        return;
                    }

                    // Check if user owns the order
                    if ($order->user_id !== $this->user()->id) {
                        $fail('You are not authorized to pay for this order.');

                        return;
                    }

                    // Check if already paid
                    if ($order->payment_status === 'paid') {
                        $fail('This order has already been paid.');

                        return;
                    }

                    // Check if payment is currently processing
                    if ($order->payment_status === 'pending') {
                        $fail('A payment is already in progress for this order.');

                        return;
                    }

                    // Only unpaid and failed orders can have payments initiated
                    if (! in_array($order->payment_status, ['unpaid', 'failed'])) {
                        $fail('This order is not eligible for payment.');

                        return;
                    }
                },
            ],
            'callback_url' => [
                'nullable',
                'url',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Please specify the order to pay for.',
            'callback_url.url' => 'The callback URL must be a valid URL.',
        ];
    }

    /**
     * Get the validated order.
     */
    public function getOrder(): Order
    {
        return Order::findOrFail($this->validated('order_id'));
    }
}
