<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Validation\Validator;

class VendorReviewIndexRequest extends BaseReviewRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rating' => ['nullable', 'numeric', 'between:1,5'],
            'has_images' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('rating')) {
                return;
            }

            $rating = (float) $this->input('rating');
            $doubledRating = $rating * 2;

            if (abs($doubledRating - round($doubledRating)) > 0.00001) {
                $validator->errors()->add('rating', 'The rating must be in 0.5 increments.');
            }
        });
    }
}
