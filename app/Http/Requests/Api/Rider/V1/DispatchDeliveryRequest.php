<?php

namespace App\Http\Requests\Api\Rider\V1;

use Illuminate\Foundation\Http\FormRequest;

class DispatchDeliveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rider_id' => ['nullable', 'exists:riders,id'],
            'pickup_address' => ['required', 'string'],
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['required', 'string'],
            'dropoff_latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
        ];
    }
}
