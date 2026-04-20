<?php

namespace App\Http\Requests\Admin;

use App\Enums\VendorVisitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideVendorVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'super_admin'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in([VendorVisitStatus::Passed->value, VendorVisitStatus::Failed->value])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
