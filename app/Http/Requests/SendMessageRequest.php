<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'in:text,image,file'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'], // 10MB max per file
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Please provide a message or attachment.',
            'body.max' => 'The message cannot be longer than 5000 characters.',
            'attachments.max' => 'You cannot send more than 10 attachments at once.',
            'attachments.*.max' => 'Each attachment cannot be larger than 10MB.',
        ];
    }
}
