<?php

namespace App\Http\Controllers;

use App\Services\AdminNotificationFeedService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationFeedController extends Controller
{
    public function __construct(private readonly AdminNotificationFeedService $service) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'categories' => ['sometimes', 'array'],
            'categories.*' => [Rule::in(['vendor_onboarding', 'tier_upgrade', 'field_agent'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $categories = array_values($validated['categories'] ?? []);
        $page = (int) ($validated['page'] ?? 1);

        $feed = $this->service->feed(
            categories: $categories,
            perPage: 30,
            page: $page,
        );

        return Inertia::render('notifications/index', [
            'feed' => $feed,
            'filters' => [
                'categories' => $categories,
            ],
        ]);
    }
}
