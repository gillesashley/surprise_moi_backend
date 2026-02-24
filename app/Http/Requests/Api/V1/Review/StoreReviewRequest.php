<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
            'reviewable_type' => ['required', 'string', 'in:product,service'],
            'reviewable_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['string', 'max:500'],
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
            'reviewable_type.required' => 'Please specify what you are reviewing (product or service).',
            'reviewable_type.in' => 'You can only review products or services.',
            'reviewable_id.required' => 'Please specify the item you are reviewing.',
            'rating.required' => 'Please provide a star rating.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
            'comment.max' => 'Your review comment cannot exceed 2000 characters.',
            'images.max' => 'You can upload a maximum of 5 images.',
        ];
    }
}
