<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $conversationId = $this->route('conversation')?->id;

        return [
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'in:text,image,file'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'], // 10MB max per file
            'reply_to_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($conversationId) {
                    if ($value && ! Message::where('id', $value)->where('conversation_id', $conversationId)->exists()) {
                        $fail('The replied-to message does not belong to this conversation.');
                    }
                },
            ],
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
