<?php

namespace App\Http\Controllers;

use App\Models\PersonalityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PersonalityTraitController extends Controller
{
    /**
     * Display a listing of personality traits.
     */
    public function index()
    {
        $personalityTraits = PersonalityTrait::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('personality-traits/index', [
            'personalityTraits' => $personalityTraits,
            'canCreate' => Auth::user()->isSuperAdmin(),
            'canDelete' => Auth::user()->isSuperAdmin(),
        ]);
    }

    /**
     * Show the form for creating a new personality trait.
     */
    public function create()
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create personality traits.');
        }

        return Inertia::render('personality-traits/create');
    }

    /**
     * Store a newly created personality trait.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create personality traits.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:personality_traits,name'],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        PersonalityTrait::create($validated);

        return redirect()->route('content-management', ['tab' => 'traits'])->with('success', 'Personality trait created successfully.');
    }

    /**
     * Show the form for editing the specified personality trait.
     */
    public function edit(PersonalityTrait $personalityTrait)
    {
        return Inertia::render('personality-traits/edit', [
            'personalityTrait' => $personalityTrait,
        ]);
    }

    /**
     * Update the specified personality trait.
     */
    public function update(Request $request, PersonalityTrait $personalityTrait)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:personality_traits,name,'.$personalityTrait->id],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        $personalityTrait->update($validated);

        return redirect()->route('content-management', ['tab' => 'traits'])->with('success', 'Personality trait updated successfully.');
    }

    /**
     * Remove the specified personality trait.
     */
    public function destroy(PersonalityTrait $personalityTrait)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete personality traits.');
        }

        $personalityTrait->users()->detach();
        $personalityTrait->delete();

        return redirect()->route('content-management', ['tab' => 'traits'])->with('success', 'Personality trait deleted successfully.');
    }
}
