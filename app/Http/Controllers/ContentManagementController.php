<?php

namespace App\Http\Controllers;

use App\Models\BespokeService;
use App\Models\Category;
use App\Models\Interest;
use App\Models\MusicGenre;
use App\Models\PersonalityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ContentManagementController extends Controller
{
    /**
     * Display the content management page with all data.
     */
    public function index(Request $request)
    {
        $categories = Category::query()
            ->select('id', 'name', 'slug', 'type', 'description', 'icon', 'image', 'is_active', 'sort_order')
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->withCount('products')
            ->orderBy('sort_order')
            ->paginate(15);

        $interests = Interest::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        $personalityTraits = PersonalityTrait::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        $musicGenres = MusicGenre::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        $bespokeServices = BespokeService::query()
            ->withCount('vendorApplications')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('content-management/index', [
            'categories' => [
                'data' => $categories->items(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
            'interests' => [
                'data' => $interests->items(),
                'current_page' => $interests->currentPage(),
                'last_page' => $interests->lastPage(),
                'per_page' => $interests->perPage(),
                'total' => $interests->total(),
            ],
            'personalityTraits' => [
                'data' => $personalityTraits->items(),
                'current_page' => $personalityTraits->currentPage(),
                'last_page' => $personalityTraits->lastPage(),
                'per_page' => $personalityTraits->perPage(),
                'total' => $personalityTraits->total(),
            ],
            'musicGenres' => [
                'data' => $musicGenres->items(),
                'current_page' => $musicGenres->currentPage(),
                'last_page' => $musicGenres->lastPage(),
                'per_page' => $musicGenres->perPage(),
                'total' => $musicGenres->total(),
            ],
            'bespokeServices' => [
                'data' => $bespokeServices->items(),
                'current_page' => $bespokeServices->currentPage(),
                'last_page' => $bespokeServices->lastPage(),
                'per_page' => $bespokeServices->perPage(),
                'total' => $bespokeServices->total(),
            ],
            'canCreate' => Auth::user()->isSuperAdmin(),
            'canDelete' => Auth::user()->isSuperAdmin(),
            'activeTab' => $request->get('tab', 'categories'),
        ]);
    }
}
