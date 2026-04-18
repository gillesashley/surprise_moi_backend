<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSupportTicketRequest extends FormRequest
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
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->whereIn('role', ['admin', 'super_admin'])
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'The selected assignee must be an admin or super admin.',
        ];
    }
}
