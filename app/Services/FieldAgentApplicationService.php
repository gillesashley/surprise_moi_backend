<?php

namespace App\Services;

use App\Models\FieldAgentApplication;
use App\Notifications\FieldAgentApplicationReceivedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class FieldAgentApplicationService
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array{ghana_card_image: UploadedFile, ghana_card_back_image: UploadedFile, selfie: UploadedFile}  $files
     */
    public function create(array $validated, array $files): FieldAgentApplication
    {
        return DB::transaction(function () use ($validated, $files) {
            $ghanaCardPath = $files['ghana_card_image']->store('field-agents/ghana-cards');
            $ghanaCardBackPath = $files['ghana_card_back_image']->store('field-agents/ghana-cards');
            $selfiePath = $files['selfie']->store('field-agents/selfies');

            $application = FieldAgentApplication::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => strtolower($validated['email']),
                'contact_number' => $validated['contact_number'],
                'region_id' => $validated['region_id'],
                'city_id' => $validated['city_id'],
                'location' => $validated['location'],
                'ghana_card_number' => $validated['ghana_card_number'],
                'ghana_card_image_path' => $ghanaCardPath,
                'ghana_card_back_image_path' => $ghanaCardBackPath,
                'selfie_path' => $selfiePath,
                'password' => Hash::make($validated['password']),
                'status' => 'pending',
            ]);

            Notification::send($application, new FieldAgentApplicationReceivedNotification($application));

            return $application;
        });
    }
}
