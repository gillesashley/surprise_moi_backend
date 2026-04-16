<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFieldAgentApplicationRequest;
use App\Models\Region;
use App\Services\FieldAgentApplicationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FieldAgentRegistrationController extends Controller
{
    public function __construct(private FieldAgentApplicationService $service) {}

    public function create(): Response
    {
        return Inertia::render('field-agent/register/index', [
            'regions' => Region::with(['cities' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(StoreFieldAgentApplicationRequest $request): RedirectResponse
    {
        $this->service->create(
            $request->safe()->except(['ghana_card_image', 'selfie', 'website', 'password_confirmation']),
            [
                'ghana_card_image' => $request->file('ghana_card_image'),
                'selfie' => $request->file('selfie'),
            ]
        );

        return redirect()->route('field-agents.register.submitted');
    }

    public function submitted(): Response
    {
        return Inertia::render('field-agent/register/submitted');
    }
}
