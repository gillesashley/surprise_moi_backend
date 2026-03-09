<?php

namespace App\Http\Requests\Api\V1\Shop;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
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
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:shops,slug'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
            'is_active' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
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
            'category_id.required' => 'Shop category is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'name.required' => 'Shop name is required.',
            'name.max' => 'Shop name must not exceed 255 characters.',
            'owner_name.required' => 'Owner name is required.',
            'owner_name.max' => 'Owner name must not exceed 255 characters.',
            'slug.unique' => 'This shop slug is already taken.',
            'logo.image' => 'Logo must be an image file.',
            'logo.max' => 'Logo size must not exceed 5MB.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
