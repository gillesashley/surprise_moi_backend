<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_CLOSED,
            ])],
            'closure_note' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn (): bool => $this->input('status') === SupportTicket::STATUS_CLOSED),
            ],
        ];
    }
}
