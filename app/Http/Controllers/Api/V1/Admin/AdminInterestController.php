<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreInterestRequest;
use App\Http\Requests\Api\V1\Admin\UpdateInterestRequest;
use App\Models\Interest;

class AdminInterestController extends Controller
{
    /**
     * Display a listing of all interests.
     */
    public function index()
    {
        $interests = Interest::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'interests' => $interests,
            ],
        ]);
    }

    /**
     * Store a newly created interest.
     */
    public function store(StoreInterestRequest $request)
    {
        $interest = Interest::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Interest created successfully.',
            'data' => [
                'interest' => $interest,
            ],
        ], 201);
    }

    /**
     * Display the specified interest.
     */
    public function show(Interest $interest)
    {
        $interest->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => [
                'interest' => $interest,
            ],
        ]);
    }

    /**
     * Update the specified interest.
     */
    public function update(UpdateInterestRequest $request, Interest $interest)
    {
        $interest->update($request->validated());
        $interest->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Interest updated successfully.',
            'data' => [
                'interest' => $interest,
            ],
        ]);
    }

    /**
     * Remove the specified interest.
     */
    public function destroy(Interest $interest)
    {
        // Detach from all users first
        $interest->users()->detach();
        $interest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Interest deleted successfully.',
        ]);
    }
}
