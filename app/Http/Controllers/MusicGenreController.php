<?php

namespace App\Http\Controllers;

use App\Models\MusicGenre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MusicGenreController extends Controller
{
    /**
     * Display a listing of music genres.
     */
    public function index()
    {
        $musicGenres = MusicGenre::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('music-genres/index', [
            'musicGenres' => $musicGenres,
            'canCreate' => Auth::user()->isSuperAdmin(),
            'canDelete' => Auth::user()->isSuperAdmin(),
        ]);
    }

    /**
     * Show the form for creating a new music genre.
     */
    public function create()
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create music genres.');
        }

        return Inertia::render('music-genres/create');
    }

    /**
     * Store a newly created music genre.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can create music genres.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:music_genres,name'],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        MusicGenre::create($validated);

        return redirect()->route('content-management', ['tab' => 'music'])->with('success', 'Music genre created successfully.');
    }

    /**
     * Show the form for editing the specified music genre.
     */
    public function edit(MusicGenre $musicGenre)
    {
        return Inertia::render('music-genres/edit', [
            'musicGenre' => $musicGenre,
        ]);
    }

    /**
     * Update the specified music genre.
     */
    public function update(Request $request, MusicGenre $musicGenre)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:music_genres,name,'.$musicGenre->id],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        $musicGenre->update($validated);

        return redirect()->route('content-management', ['tab' => 'music'])->with('success', 'Music genre updated successfully.');
    }

    /**
     * Remove the specified music genre.
     */
    public function destroy(MusicGenre $musicGenre)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete music genres.');
        }

        $musicGenre->users()->detach();
        $musicGenre->delete();

        return redirect()->route('content-management', ['tab' => 'music'])->with('success', 'Music genre deleted successfully.');
    }
}
