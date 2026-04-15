<?php

namespace App\Http\Requests;

use App\Enums\FieldAgentApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreFieldAgentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ghana_card_number' => strtoupper(trim((string) $this->ghana_card_number)),
            'contact_number' => $this->normalizePhone((string) $this->contact_number),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'required', 'email:rfc', 'max:120',
                Rule::unique('users', 'email'),
                Rule::unique('field_agent_applications', 'email')
                    ->whereIn('status', FieldAgentApplicationStatus::nonRejectedStatuses()),
            ],
            'contact_number' => ['required', 'regex:/^\+233\d{9}$/'],
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'city_id' => [
                'required', 'integer',
                Rule::exists('cities', 'id')->where(fn ($q) => $q->where('region_id', $this->integer('region_id'))),
            ],
            'location' => ['required', 'string', 'max:160'],
            'ghana_card_number' => [
                'required', 'regex:/^GHA-\d{9}-\d$/',
                Rule::unique('field_agent_applications', 'ghana_card_number')
                    ->whereIn('status', FieldAgentApplicationStatus::nonRejectedStatuses()),
            ],
            'ghana_card_image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'selfie' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Please enter a valid Ghana phone number (e.g. 0551234567 or +233551234567).',
            'ghana_card_number.regex' => 'Ghana card number must be in the format GHA-XXXXXXXXX-X.',
            'city_id.exists' => 'Please choose a city that belongs to the selected region.',
            'website.max' => 'Spam detected.',
        ];
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+233'.substr($digits, 1);
        }

        return $raw;
    }
}
