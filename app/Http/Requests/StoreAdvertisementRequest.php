<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvertisementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'super_admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'], // 5MB max
            'link_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', 'in:active,inactive,scheduled'],
            'placement' => ['required', 'in:home_banner,feed,popup,sidebar'],
            'target_audience' => ['nullable', 'array'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Advertisement title is required.',
            'image.image' => 'The file must be an image.',
            'image.max' => 'The image must not be larger than 5MB.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
