<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('storage_url')) {
    /**
     * Generate a URL for a stored file path.
     *
     * If the path is already a full URL (http/https), returns it as-is.
     * Otherwise, delegates to Storage::url() for the configured disk.
     */
    function storage_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    }
}
