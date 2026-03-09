<?php

namespace App\Observers;

use App\Models\VendorApplication;
use Illuminate\Support\Facades\Storage;

class VendorApplicationObserver
{
    /**
     * Handle the VendorApplication "forceDeleting" event.
     *
     * Cleans up all stored files from R2 before the record is permanently deleted.
     */
    public function forceDeleting(VendorApplication $application): void
    {
        $fileFields = [
            'ghana_card_front',
            'ghana_card_back',
            'selfie_image',
            'proof_of_business',
            'business_certificate_document',
        ];

        foreach ($fileFields as $field) {
            if ($application->{$field}) {
                Storage::disk()->delete($application->{$field});
            }
        }
    }
}
