<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Validation\Validator;

class UpdateReviewRequest extends BaseReviewRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review && $this->user() && $review->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_type' => ['required', 'string', 'in:product,service'],
            'item_id' => ['required', 'integer'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'numeric', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'item_type.required' => 'Please specify what you are reviewing (product or service).',
            'item_type.in' => 'You can only review products or services.',
            'item_id.required' => 'Please specify the item you are reviewing.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
            'comment.max' => 'Your review comment cannot exceed 2000 characters.',
            'images.max' => 'You can upload a maximum of 5 images.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $itemType = $this->input('item_type', $this->input('reviewable_type'));
        $itemId = $this->input('item_id', $this->input('reviewable_id'));

        $this->merge([
            'item_type' => is_string($itemType) ? strtolower($itemType) : $itemType,
            'item_id' => $itemId,
        ]);
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
