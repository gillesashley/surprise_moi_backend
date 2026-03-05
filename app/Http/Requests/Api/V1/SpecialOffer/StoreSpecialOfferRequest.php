<?php

namespace App\Http\Requests\Api\V1\SpecialOffer;

use App\Models\SpecialOffer;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpecialOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isVendor();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'exists:products,id',
                function ($attribute, $value, $fail) {
                    $product = \App\Models\Product::with('shop')->find($value);

                    if (! $product || ! $product->shop || $product->shop->vendor_id !== $this->user()->id) {
                        $fail('This product does not belong to your shop.');

                        return;
                    }

                    $hasActiveOffer = SpecialOffer::where('product_id', $value)
                        ->where('is_active', true)
                        ->where('ends_at', '>=', now())
                        ->exists();

                    if ($hasActiveOffer) {
                        $fail('This product already has an active special offer.');
                    }
                },
            ],
            'discount_percentage' => ['required', 'integer', 'min:1', 'max:99'],
            'tag' => ['required', 'in:'.implode(',', SpecialOffer::validTags())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Please select a product.',
            'product_id.exists' => 'The selected product does not exist.',
            'discount_percentage.required' => 'Discount percentage is required.',
            'discount_percentage.min' => 'Discount must be at least 1%.',
            'discount_percentage.max' => 'Discount cannot exceed 99%.',
            'tag.required' => 'Please select an offer tag.',
            'tag.in' => 'The selected tag is not valid.',
            'starts_at.required' => 'Start date is required.',
            'ends_at.required' => 'End date is required.',
            'ends_at.after' => 'End date must be after the start date and in the future.',
        ];
    }
}
