<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');
        $user = $this->user();

        return $user->role === 'admin' ||
            ($user->role === 'vendor' && $order->vendor_id === $user->id);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,confirmed,processing,fulfilled,shipped,delivered,refunded'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Please select a status.',
            'status.in' => 'Invalid order status.',
        ];
    }
}
