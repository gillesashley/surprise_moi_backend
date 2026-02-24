<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'min:1'],
            'item_type' => ['required', 'in:product,service'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'Item ID is required.',
            'item_id.integer' => 'Item ID must be a valid number.',
            'item_type.required' => 'Item type is required.',
            'item_type.in' => 'Item type must be either "product" or "service".',
        ];
    }
}
