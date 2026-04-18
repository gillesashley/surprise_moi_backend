<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'priority' => ['nullable', Rule::in([
                SupportTicket::PRIORITY_LOW,
                SupportTicket::PRIORITY_NORMAL,
                SupportTicket::PRIORITY_HIGH,
            ])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
