<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_id.required' => 'Please specify the vendor to start a conversation with.',
            'vendor_id.exists' => 'The specified vendor does not exist.',
            'message.max' => 'The message cannot be longer than 5000 characters.',
        ];
    }
}
