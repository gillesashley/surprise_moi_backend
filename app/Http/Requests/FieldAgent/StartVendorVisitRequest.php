<?php

namespace App\Http\Requests\FieldAgent;

use Illuminate\Foundation\Http\FormRequest;

class StartVendorVisitRequest extends FormRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Location is required to start a visit.',
            'longitude.required' => 'Location is required to start a visit.',
        ];
    }
}
