<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorVisitItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'field_agent';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'passed' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
