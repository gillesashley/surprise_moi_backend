<?php

namespace App\Http\Controllers;

use App\Models\FieldAgentApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FieldAgentVerificationController extends Controller
{
    public function show(Request $request): Response
    {
        $application = FieldAgentApplication::with(['region:id,name', 'city:id,name'])
            ->where('approved_user_id', $request->user()->id)
            ->latest()
            ->first();

        if ($application) {
            $disk = Storage::disk(config('filesystems.default'));
            $data = $application->toArray();
            $data['ghana_card_image_url'] = $disk->url($application->ghana_card_image_path);
            $data['ghana_card_back_image_url'] = $application->ghana_card_back_image_path
                ? $disk->url($application->ghana_card_back_image_path)
                : null;
            $data['selfie_url'] = $disk->url($application->selfie_path);

            // Ensure reviewed_at and created_at are formatted correctly
            $data['created_at_formatted'] = $application->created_at?->format('M d, Y');
            $data['reviewed_at_formatted'] = $application->reviewed_at?->format('M d, Y');
        }

        return Inertia::render('field-agent/verification', [
            'application' => $application ? $data : null,
        ]);
    }
}
