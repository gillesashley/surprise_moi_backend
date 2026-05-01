<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlagVendorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, ['admin', 'super_admin'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'flag_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flag_reason.required' => 'Please tell the vendor what is missing or unclear.',
            'flag_reason.min' => 'Please provide at least 10 characters of detail.',
            'flag_reason.max' => 'The reason cannot exceed 1000 characters.',
        ];
    }
}
