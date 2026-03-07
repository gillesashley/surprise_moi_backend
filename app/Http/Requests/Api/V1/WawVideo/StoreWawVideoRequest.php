<?php

namespace App\Http\Requests\Api\V1\WawVideo;

use Illuminate\Foundation\Http\FormRequest;

class StoreWawVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasApprovedVendorApplication();
    }

    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm', 'max:51200'],
            'thumbnail' => ['nullable', 'file', 'image', 'max:5120'],
            'caption' => ['required', 'string', 'max:200'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required' => 'A video file is required.',
            'video.mimetypes' => 'The video must be an MP4, MOV, AVI, or WebM file.',
            'video.max' => 'The video must not exceed 50MB.',
            'thumbnail.image' => 'The thumbnail must be an image.',
            'thumbnail.max' => 'The thumbnail must not exceed 5MB.',
            'caption.required' => 'A caption is required.',
            'caption.max' => 'The caption must not exceed 200 characters.',
            'product_id.exists' => 'The selected product does not exist.',
            'service_id.exists' => 'The selected service does not exist.',
        ];
    }
}
