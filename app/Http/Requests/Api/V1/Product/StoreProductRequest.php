<?php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'detailed_description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', 'in:GHS,USD,EUR,GBP'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:50'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['string', 'max:50'],
            'free_delivery' => ['nullable', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery_days' => ['nullable', 'string', 'max:100'],
            'return_policy' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
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
            'shop_id.required' => 'Please select a shop for this product.',
            'shop_id.exists' => 'The selected shop does not exist.',
            'category_id.required' => 'Please select a product category.',
            'category_id.exists' => 'The selected category does not exist.',
            'name.required' => 'Product name is required.',
            'description.required' => 'Product description is required.',
            'price.required' => 'Product price is required.',
            'price.min' => 'Price must be at least 0.',
            'discount_price.lt' => 'Discount price must be less than the regular price.',
            'stock.required' => 'Stock quantity is required.',
            'stock.min' => 'Stock cannot be negative.',
            'thumbnail.image' => 'Thumbnail must be an image file.',
            'thumbnail.max' => 'Thumbnail size must not exceed 5MB.',
            'images.*.image' => 'All files must be images.',
            'images.*.max' => 'Each image must not exceed 5MB.',
            'images.max' => 'You can upload a maximum of 10 images.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Calculate discount percentage if discount price is provided
        if ($this->discount_price && $this->price) {
            $calculatedPercentage = round((($this->price - $this->discount_price) / $this->price) * 100);
            $this->merge([
                'discount_percentage' => $this->discount_percentage ?? $calculatedPercentage,
            ]);
        }
    }
}
