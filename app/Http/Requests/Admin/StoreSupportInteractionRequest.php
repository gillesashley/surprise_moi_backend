<?php

namespace App\Http\Requests\Admin;

use App\Models\SupportInteraction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(SupportInteraction::CHANNELS)],
            'direction' => ['required', Rule::in([
                SupportInteraction::DIRECTION_INBOUND,
                SupportInteraction::DIRECTION_OUTBOUND,
            ])],
            'summary' => ['required', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
