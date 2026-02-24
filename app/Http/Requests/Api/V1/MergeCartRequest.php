<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MergeCartRequest extends FormRequest
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
            'guest_cart_token' => ['required', 'uuid', 'exists:carts,cart_token'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_cart_token.required' => 'Guest cart token is required',
            'guest_cart_token.uuid' => 'Guest cart token must be a valid UUID',
            'guest_cart_token.exists' => 'Guest cart not found',
        ];
    }
}
