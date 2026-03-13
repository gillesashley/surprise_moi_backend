<?php

namespace App\Http\Requests\Api\V1\TierUpgrade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RejectTierUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'admin_notes' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admin_notes.required' => 'Please provide a reason for the rejection.',
            'admin_notes.string' => 'The rejection reason must be valid text.',
            'admin_notes.max' => 'The rejection reason cannot exceed 1000 characters.',
        ];
    }
}
