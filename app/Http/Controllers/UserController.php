<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Available roles for users.
     */
    private const ROLES = ['customer', 'vendor', 'admin', 'super_admin', 'influencer', 'field_agent', 'marketer'];

    /**
     * Display a listing of users, optionally filtered by role.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'created_at']);

        // Role filtering
        $roleFilter = $request->input('role');
        if ($roleFilter) {
            $roles = array_intersect(explode(',', $roleFilter), self::ROLES);
            if (! empty($roles)) {
                $query->whereIn('role', $roles);
            }
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting functionality
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['name', 'email', 'phone', 'role', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => self::ROLES,
            'canDelete' => Auth::user()->isSuperAdmin(),
            'activeRole' => $roleFilter,
            'filters' => [
                'search' => $request->input('search'),
                'role' => $roleFilter,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Export users list as a PDF file.
     */
    public function exportPdf(Request $request): \Illuminate\Http\Response
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'created_at']);

        // Role filtering (same logic as index)
        $roleFilter = $request->input('role');
        if ($roleFilter) {
            $roles = array_intersect(explode(',', $roleFilter), self::ROLES);
            if (! empty($roles)) {
                $query->whereIn('role', $roles);
            }
        }

        // Search functionality (same logic as index)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting (same logic as index)
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['name', 'email', 'phone', 'role', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $users = $query->get();

        // Role breakdown stats
        $roleBreakdown = $users->groupBy('role')->map->count();

        $pdf = Pdf::loadView('exports.users-pdf', [
            'users' => $users,
            'totalUsers' => $users->count(),
            'roleBreakdown' => $roleBreakdown,
            'roleFilter' => $roleFilter,
            'searchFilter' => $request->input('search'),
            'generatedAt' => now()->format('F j, Y \a\t g:i A'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('users-report.pdf');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['interests', 'personalityTraits', 'shops', 'products', 'services', 'latestVendorApplication', 'musicGenres', 'addresses']);
        $user->loadCount(['orders', 'reviews', 'wishlists']);

        $totalSpent = $user->orders()->sum('total');
        $avgRating = $user->reviews()->avg('rating');

        $recentOrders = $user->orders()
            ->latest()
            ->take(5)
            ->get(['id', 'order_number', 'status', 'total', 'currency', 'created_at']);

        $recentReviews = $user->reviews()
            ->latest()
            ->take(3)
            ->get(['id', 'rating', 'comment', 'created_at']);

        $vendorApplication = null;
        if ($user->latestVendorApplication) {
            $app = $user->latestVendorApplication;
            $vendorApplication = [
                'id' => $app->id,
                'status' => $app->status,
                'current_step' => $app->current_step,
                'completed_step' => $app->completed_step,
                'is_registered_vendor' => $app->isRegisteredVendor(),

                // Step 1: Ghana Card
                'ghana_card_front' => $app->ghana_card_front
                    ? Storage::url($app->ghana_card_front)
                    : null,
                'ghana_card_back' => $app->ghana_card_back
                    ? Storage::url($app->ghana_card_back)
                    : null,

                // Step 2: Business Registration Flags
                'has_business_certificate' => $app->has_business_certificate,
                'has_tin' => $app->has_tin,

                // Step 3A: Registered Vendor Documents
                'business_certificate_document' => $app->business_certificate_document
                    ? Storage::url($app->business_certificate_document)
                    : null,
                'tin_document' => $app->tin_document
                    ? Storage::url($app->tin_document)
                    : null,

                // Step 3B: Unregistered Vendor Documents
                'selfie_image' => $app->selfie_image
                    ? Storage::url($app->selfie_image)
                    : null,
                'proof_of_business' => $app->proof_of_business
                    ? Storage::url($app->proof_of_business)
                    : null,
                'mobile_money_number' => $app->mobile_money_number,
                'mobile_money_provider' => $app->mobile_money_provider,

                // Social Media
                'facebook_handle' => $app->facebook_handle,
                'instagram_handle' => $app->instagram_handle,
                'twitter_handle' => $app->twitter_handle,

                // Review Details
                'submitted_at' => $app->submitted_at,
                'reviewed_at' => $app->reviewed_at,
                'rejection_reason' => $app->rejection_reason,
            ];
        }

        return Inertia::render('users/show', [
            'user' => [
                ...$user->only(['id', 'name', 'email', 'phone', 'role', 'bio', 'date_of_birth', 'gender', 'favorite_color', 'favorite_music_genre', 'email_verified_at', 'phone_verified_at', 'is_popular', 'created_at', 'updated_at']),
                'avatar' => storage_url($user->avatar),
                'provider' => $user->provider,
                'orders_count' => $user->orders_count,
                'reviews_count' => $user->reviews_count,
                'wishlists_count' => $user->wishlists_count,
                'total_spent' => (float) $totalSpent,
                'avg_rating' => $avgRating ? round((float) $avgRating, 1) : null,
                'recent_orders' => $recentOrders,
                'recent_reviews' => $recentReviews,
                'addresses' => $user->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'name' => $a->name,
                    'address_line_1' => $a->address_line_1,
                    'city' => $a->city,
                    'state' => $a->state,
                    'postal_code' => $a->postal_code,
                    'country' => $a->country,
                    'is_default' => $a->is_default,
                ]),
                'music_genres' => $user->musicGenres->map(fn ($g) => ['id' => $g->id, 'name' => $g->name]),
                'interests' => $user->interests->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'icon' => $i->icon]),
                'personality_traits' => $user->personalityTraits->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'icon' => $t->icon]),
                'shops' => $user->shops->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'location' => $s->location,
                    'is_active' => $s->is_active,
                    'products_count' => $s->products()->count(),
                    'services_count' => $s->services()->count(),
                ]),
                'products_count' => $user->products()->count(),
                'services_count' => $user->services()->count(),
                'vendor_application' => $vendorApplication,
            ],
            'roles' => self::ROLES,
            'canDelete' => Auth::user()->isSuperAdmin(),
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        // Only super_admin can edit super_admin users
        if ($user->isSuperAdmin() && ! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'You do not have permission to edit super admin users.');
        }

        return Inertia::render('users/edit', [
            'user' => [
                ...$user->only(['id', 'name', 'email', 'phone', 'role', 'bio', 'date_of_birth', 'gender', 'is_popular']),
                'avatar' => storage_url($user->avatar),
            ],
            'roles' => self::ROLES,
            'canEditRole' => Auth::user()->isSuperAdmin() || ! $user->isAdmin(),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();

        // Only super_admin can edit super_admin users
        if ($user->isSuperAdmin() && ! $currentUser->isSuperAdmin()) {
            return back()->with('error', 'You do not have permission to edit super admin users.');
        }

        // Determine which roles the current user can assign
        $allowedRoles = $currentUser->isSuperAdmin()
            ? self::ROLES
            : ['customer', 'vendor']; // Regular admins can only assign customer/vendor

        // If current user is not super_admin and trying to modify an admin's role
        if (! $currentUser->isSuperAdmin() && $user->isAdmin()) {
            $allowedRoles = [$user->role]; // Can only keep the current role
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:'.implode(',', $allowedRoles)],
            'bio' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'is_popular' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'r2');
            $validated['avatar'] = $avatarPath;
        }

        // Prevent demoting yourself from super_admin
        if ($user->id === $currentUser->id && $user->isSuperAdmin() && $validated['role'] !== 'super_admin') {
            return back()->with('error', 'You cannot demote yourself from super admin.');
        }

        $user->update($validated);

        return redirect()->route('users.show', $user)->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        // Only super_admin can delete users
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete users.');
        }

        // Prevent deleting your own account
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
