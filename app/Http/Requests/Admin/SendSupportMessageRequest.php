<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
            'template_key' => ['nullable', Rule::in(SupportMessage::TEMPLATE_KEYS)],
            'to_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
