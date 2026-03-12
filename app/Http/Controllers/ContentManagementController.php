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
        $search = $request->input('search');

        $categories = Category::query()
            ->select('id', 'name', 'slug', 'type', 'description', 'icon', 'image', 'is_active', 'sort_order')
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('products')
            ->orderBy('sort_order')
            ->paginate(15, ['*'], 'categories_page');

        // Normalize icon paths to proper URLs (public assets vs storage files)
        $categories->getCollection()->transform(function ($category) {
            if ($category->icon && ! str_starts_with($category->icon, 'http') && ! str_starts_with($category->icon, '/')) {
                $category->icon = file_exists(public_path($category->icon))
                    ? '/'.$category->icon
                    : '/storage/'.$category->icon;
            }

            return $category;
        });

        $interests = Interest::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15, ['*'], 'interests_page');

        $personalityTraits = PersonalityTrait::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15, ['*'], 'traits_page');

        $musicGenres = MusicGenre::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15, ['*'], 'music_page');

        $bespokeServices = BespokeService::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('vendorApplications')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15, ['*'], 'bespoke_page');

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
            'search' => $search ?? '',
        ]);
    }
}
