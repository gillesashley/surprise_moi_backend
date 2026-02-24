<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientErrorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'device_info' => ['sometimes', 'nullable', 'array'],
            'time' => ['sometimes', 'nullable', 'date'],
            'error' => ['required', 'string'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If device_info or payload sent as JSON string, attempt decode
        if (is_string($this->device_info)) {
            $decoded = json_decode($this->device_info, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['device_info' => $decoded]);
            }
        }

        if (is_string($this->payload)) {
            $decoded = json_decode($this->payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['payload' => $decoded]);
            }
        }
    }
}
