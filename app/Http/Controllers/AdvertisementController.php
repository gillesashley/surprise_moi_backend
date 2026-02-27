<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdvertisementRequest;
use App\Http\Requests\UpdateAdvertisementRequest;
use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdvertisementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $advertisements = Advertisement::query()
            ->with('creator')
            ->withTrashed()
            ->orderBy('display_order')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('advertisements/index', [
            'advertisements' => $advertisements,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('advertisements/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdvertisementRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('advertisements');
        }

        $data['created_by'] = auth()->id();

        Advertisement::create($data);

        return redirect()->route('dashboard.advertisements.index')
            ->with('success', 'Advertisement created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Advertisement $advertisement): Response
    {
        $advertisement->load('creator');

        return Inertia::render('advertisements/show', [
            'advertisement' => $advertisement,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Advertisement $advertisement): Response
    {
        return Inertia::render('advertisements/edit', [
            'advertisement' => $advertisement,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdvertisementRequest $request, Advertisement $advertisement): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($advertisement->image_path) {
                Storage::disk()->delete($advertisement->image_path);
            }

            $data['image_path'] = $request->file('image')->store('advertisements');
        }

        $advertisement->update($data);

        return redirect()->route('dashboard.advertisements.index')
            ->with('success', 'Advertisement updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        // Delete associated image if exists
        if ($advertisement->image_path) {
            Storage::disk()->delete($advertisement->image_path);
        }

        $advertisement->delete();

        return redirect()->route('dashboard.advertisements.index')
            ->with('success', 'Advertisement deleted successfully.');
    }
}
