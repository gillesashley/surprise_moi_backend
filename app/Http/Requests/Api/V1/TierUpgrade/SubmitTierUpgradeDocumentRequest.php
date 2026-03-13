<?php

namespace App\Http\Requests\Api\V1\TierUpgrade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubmitTierUpgradeDocumentRequest extends FormRequest
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
            'business_certificate_document' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_certificate_document.required' => 'A business certificate document is required.',
            'business_certificate_document.file' => 'The business certificate must be a valid file.',
            'business_certificate_document.mimes' => 'The business certificate must be a JPEG, PNG, JPG, or PDF file.',
            'business_certificate_document.max' => 'The business certificate must not exceed 10MB.',
        ];
    }
}
