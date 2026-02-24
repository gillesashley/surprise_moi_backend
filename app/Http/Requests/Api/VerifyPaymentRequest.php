<?php

namespace App\Http\Requests\Api;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated and own the payment
        $payment = Payment::where('reference', $this->input('reference'))->first();

        if (! $payment) {
            return true; // Let validation handle non-existence
        }

        return $this->user()->id === $payment->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:100',
                Rule::exists('payments', 'reference'),
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
            'reference.required' => 'Payment reference is required.',
            'reference.exists' => 'Payment reference not found.',
        ];
    }

    /**
     * Get the validated payment.
     */
    public function getPayment(): Payment
    {
        return Payment::where('reference', $this->validated('reference'))->firstOrFail();
    }
}
