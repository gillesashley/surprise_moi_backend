<?php

namespace App\Http\Requests\Api\V1\Service;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasApprovedVendorApplication();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'service_type' => ['required', 'string', 'max:255'],
            'charge_start' => ['required', 'numeric', 'min:0'],
            'charge_end' => ['nullable', 'numeric', 'min:0', 'gte:charge_start'],
            'currency' => ['nullable', 'string', 'size:3', 'in:GHS,USD,EUR,GBP'],
            'availability' => ['nullable', 'in:available,unavailable,booked'],
            'thumbnail' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_id.required' => 'Please select a shop for this service.',
            'shop_id.exists' => 'The selected shop does not exist.',
            'name.required' => 'Service name is required.',
            'description.required' => 'Service description is required.',
            'service_type.required' => 'Service type is required.',
            'charge_start.required' => 'Minimum price is required.',
            'charge_start.min' => 'Minimum price must be at least 0.',
            'charge_end.gte' => 'Maximum price must be greater than or equal to minimum price.',
            'thumbnail.image' => 'Thumbnail must be an image file.',
            'thumbnail.max' => 'Thumbnail size must not exceed 5MB.',
            'availability.in' => 'Availability must be either available, unavailable, or booked.',
        ];
    }
}
