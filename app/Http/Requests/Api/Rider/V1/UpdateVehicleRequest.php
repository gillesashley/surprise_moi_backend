<?php

namespace App\Http\Requests\Api\Rider\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
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
            'vehicle_type' => ['sometimes', 'string', 'max:100'],
            'vehicle_category' => ['sometimes', 'in:motorbike,car'],
            'license_plate' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
