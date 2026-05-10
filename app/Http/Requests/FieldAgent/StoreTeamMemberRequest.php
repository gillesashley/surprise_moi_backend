<?php

namespace App\Http\Requests\FieldAgent;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTeam') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizePhone((string) $this->input('phone')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', $this->eligibilityRule('email')],
            'phone' => ['required', 'regex:/^\+233\d{9}$/', $this->eligibilityRule('phone')],
            'location' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid Ghana phone number (e.g. 0551234567 or +233551234567).',
        ];
    }

    /**
     * Cross-field checks that individual eligibility rules cannot catch.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $email = (string) $this->input('email');
                $phone = (string) $this->input('phone');

                $byEmail = User::where('email', strtolower($email))->first();
                $byPhone = User::where('phone', $phone)->first();

                if ($byEmail && $byPhone && $byEmail->id !== $byPhone->id) {
                    $validator->errors()->add(
                        'email',
                        'The email and phone number belong to different registered users. Please verify the details.'
                    );
                }
            },
        ];
    }

    /**
     * Allow an existing user to be added as a team member unless they are
     * already a team member, a lead, the authenticated user, or an admin.
     */
    private function eligibilityRule(string $column): Closure
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $normalized = $column === 'email' ? strtolower((string) $value) : $value;
            $existing = User::where($column, $normalized)->first();

            if (! $existing) {
                return;
            }

            if ($existing->id === $this->user()?->id) {
                $fail('You cannot add yourself as a team member.');

                return;
            }

            if (in_array($existing->role, ['admin', 'super_admin'], true)) {
                $fail("A user with this {$attribute} is an administrator and cannot be added as a team member.");

                return;
            }

            if ($existing->parent_user_id !== null) {
                $fail("A user with this {$attribute} is already a team member.");

                return;
            }

            if ((bool) $existing->is_team_field_agent) {
                $fail("A user with this {$attribute} is already a team lead.");

                return;
            }
        };
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
