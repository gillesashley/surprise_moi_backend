<?php

namespace App\Http\Requests\Api\V1\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShopRequest extends FormRequest
{
    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $shop = $this->route('shop');

        return $this->user()
            && $this->user()->role === 'vendor'
            && $shop->vendor_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shopId = $this->route('shop')->id;

        $rules = [
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:shops,slug,'.$shopId],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
            'is_active' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'service_hours' => ['sometimes', 'array'],
        ];

        foreach (self::DAYS as $day) {
            $rules["service_hours.{$day}"] = ['required_with:service_hours', 'array'];
            $rules["service_hours.{$day}.is_open"] = ['required_with:service_hours', 'boolean'];
            $rules["service_hours.{$day}.open"] = ['nullable', 'date_format:H:i'];
            $rules["service_hours.{$day}.close"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $serviceHours = $this->input('service_hours');

            if ($serviceHours === null) {
                return;
            }

            // Reject extra keys beyond the 7 valid days
            $extraKeys = array_diff(array_keys($serviceHours), self::DAYS);
            if (! empty($extraKeys)) {
                $validator->errors()->add('service_hours', 'Service hours contains invalid day keys: '.implode(', ', $extraKeys));

                return;
            }

            foreach (self::DAYS as $day) {
                if (! isset($serviceHours[$day])) {
                    continue; // Already caught by required_with
                }

                $dayData = $serviceHours[$day];
                $isOpen = filter_var($dayData['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $open = $dayData['open'] ?? null;
                $close = $dayData['close'] ?? null;

                if ($isOpen && ($open === null || $close === null)) {
                    $validator->errors()->add(
                        "service_hours.{$day}",
                        "Opening and closing times are required when {$day} is marked as open."
                    );
                }

                if (! $isOpen && ($open !== null || $close !== null)) {
                    $validator->errors()->add(
                        "service_hours.{$day}",
                        "Times must be null when {$day} is marked as closed."
                    );
                }

                if ($open !== null && $close !== null && $close <= $open) {
                    $validator->errors()->add(
                        "service_hours.{$day}.close",
                        "Closing time must be after opening time for {$day}."
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'category_id.required' => 'Shop category is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'name.required' => 'Shop name is required.',
            'name.max' => 'Shop name must not exceed 255 characters.',
            'owner_name.required' => 'Owner name is required.',
            'owner_name.max' => 'Owner name must not exceed 255 characters.',
            'slug.unique' => 'This shop slug is already taken.',
            'logo.image' => 'Logo must be an image file.',
            'logo.max' => 'Logo size must not exceed 5MB.',
            'email.email' => 'Please provide a valid email address.',
        ];

        foreach (self::DAYS as $day) {
            $dayLabel = ucfirst($day);
            $messages["service_hours.{$day}.required_with"] = "{$dayLabel} schedule is required when updating service hours.";
            $messages["service_hours.{$day}.is_open.required_with"] = "The open/closed status for {$dayLabel} is required.";
            $messages["service_hours.{$day}.open.date_format"] = "Opening time for {$dayLabel} must be in HH:MM format (e.g., 09:00).";
            $messages["service_hours.{$day}.close.date_format"] = "Closing time for {$dayLabel} must be in HH:MM format (e.g., 17:00).";
        }

        return $messages;
    }
}
