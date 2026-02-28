<?php

namespace App\Http\Requests\Api\V1\Review;

class ListItemReviewsRequest extends BaseReviewRequest
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
            'item_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'in:product,service'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
}
