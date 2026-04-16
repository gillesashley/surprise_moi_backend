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

        return Inertia::render('field-agent/verification', [
            'application' => $application ? array_merge($application->toArray(), [
                'ghana_card_image_url' => Storage::disk('public')->url($application->ghana_card_image_path),
                'ghana_card_back_image_url' => $application->ghana_card_back_image_path
                    ? Storage::disk('public')->url($application->ghana_card_back_image_path)
                    : null,
                'selfie_url' => Storage::disk('public')->url($application->selfie_path),
            ]) : null,
        ]);
    }
}
