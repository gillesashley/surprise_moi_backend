<?php

namespace App\Http\Controllers;

use App\Models\Interest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InterestController extends Controller
{
    /**
     * Display a listing of interests.
     */
    public function index()
    {
        $interests = Interest::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('interests/index', [
            'interests' => $interests,
            'canCreate' => Auth::user()->isSuperAdmin(),
            'canDelete' => Auth::user()->isSuperAdmin(),
        ]);
    }

    /**
     * Show the form for creating a new interest.
     */
    public function create()
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create interests.');
        }

        return Inertia::render('interests/create');
    }

    /**
     * Store a newly created interest.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create interests.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:interests,name'],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        Interest::create($validated);

        return redirect()->route('content-management', ['tab' => 'interests'])->with('success', 'Interest created successfully.');
    }

    /**
     * Show the form for editing the specified interest.
     */
    public function edit(Interest $interest)
    {
        return Inertia::render('interests/edit', [
            'interest' => $interest,
        ]);
    }

    /**
     * Update the specified interest.
     */
    public function update(Request $request, Interest $interest)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:interests,name,'.$interest->id],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        $interest->update($validated);

        return redirect()->route('content-management', ['tab' => 'interests'])->with('success', 'Interest updated successfully.');
    }

    /**
     * Remove the specified interest.
     */
    public function destroy(Interest $interest)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete interests.');
        }

        $interest->users()->detach();
        $interest->delete();

        return redirect()->route('content-management', ['tab' => 'interests'])->with('success', 'Interest deleted successfully.');
    }
}
