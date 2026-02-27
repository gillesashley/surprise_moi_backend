<?php

namespace App\Http\Controllers;

use App\Models\BespokeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BespokeServiceController extends Controller
{
    /**
     * Display a listing of bespoke services.
     */
    public function index()
    {
        $bespokeServices = BespokeService::query()
            ->withCount('vendorApplications')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('bespoke-services/index', [
            'bespokeServices' => $bespokeServices,
            'canCreate' => Auth::user()->isSuperAdmin(),
            'canDelete' => Auth::user()->isSuperAdmin(),
        ]);
    }

    /**
     * Show the form for creating a new bespoke service.
     */
    public function create()
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create bespoke services.');
        }

        return Inertia::render('bespoke-services/create');
    }

    /**
     * Store a newly created bespoke service.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create bespoke services.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:bespoke_services,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Generate slug from name
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('bespoke-services');
        }

        BespokeService::create($validated);

        return redirect()->route('content-management', ['tab' => 'bespoke'])->with('success', 'Bespoke service created successfully.');
    }

    /**
     * Show the form for editing the specified bespoke service.
     */
    public function edit(BespokeService $bespokeService)
    {
        return Inertia::render('bespoke-services/edit', [
            'bespokeService' => $bespokeService,
        ]);
    }

    /**
     * Update the specified bespoke service.
     */
    public function update(Request $request, BespokeService $bespokeService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:bespoke_services,name,' . $bespokeService->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Update slug if name changed
        if ($validated['name'] !== $bespokeService->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($bespokeService->image) {
                Storage::disk()->delete($bespokeService->image);
            }
            $validated['image'] = $request->file('image')->store('bespoke-services');
        }

        $bespokeService->update($validated);

        return redirect()->route('content-management', ['tab' => 'bespoke'])->with('success', 'Bespoke service updated successfully.');
    }

    /**
     * Remove the specified bespoke service.
     */
    public function destroy(BespokeService $bespokeService)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete bespoke services.');
        }

        $bespokeService->vendorApplications()->detach();
        $bespokeService->delete();

        return redirect()->route('content-management', ['tab' => 'bespoke'])->with('success', 'Bespoke service deleted successfully.');
    }
}
