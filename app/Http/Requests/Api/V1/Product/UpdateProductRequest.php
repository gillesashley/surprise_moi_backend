<?php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user() &&
            $this->user()->role === 'vendor' &&
            $product &&
            $product->vendor_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['sometimes', 'required', 'exists:shops,id'],
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'detailed_description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', 'in:GHS,USD,EUR,GBP'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
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
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['exists:product_images,id'],
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
            'category_id.exists' => 'The selected category does not exist.',
            'price.min' => 'Price must be at least 0.',
            'discount_price.lt' => 'Discount price must be less than the regular price.',
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
