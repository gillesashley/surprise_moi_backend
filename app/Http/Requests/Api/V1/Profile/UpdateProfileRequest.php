<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'regex:/^0[0-9]{9}$/', 'unique:users,phone,'.$userId],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', 'in:male,female'],
            'bio' => ['sometimes', 'string', 'max:500'],
            'avatar' => ['sometimes', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'banner' => ['sometimes', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'favorite_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'favorite_music_genre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'interests' => ['sometimes', 'array'],
            'interests.*' => ['string', 'exists:interests,name'],
            'personality_traits' => ['sometimes', 'array'],
            'personality_traits.*' => ['string', 'exists:personality_traits,name'],
        ];
    }
}
