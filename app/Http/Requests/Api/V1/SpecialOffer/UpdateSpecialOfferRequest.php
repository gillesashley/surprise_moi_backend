<?php

namespace App\Http\Requests\Api\V1\SpecialOffer;

use App\Models\SpecialOffer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecialOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('special_offer');

        return $this->user()
            && $this->user()->isVendor()
            && $this->user()->can('update', $offer);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discount_percentage' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'tag' => ['sometimes', 'in:'.implode(',', SpecialOffer::validTags())],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discount_percentage.min' => 'Discount must be at least 1%.',
            'discount_percentage.max' => 'Discount cannot exceed 99%.',
            'tag.in' => 'The selected tag is not valid.',
            'ends_at.after' => 'End date must be after the start date and in the future.',
        ];
    }
}
