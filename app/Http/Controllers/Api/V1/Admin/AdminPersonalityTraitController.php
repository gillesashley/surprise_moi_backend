<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePersonalityTraitRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePersonalityTraitRequest;
use App\Models\PersonalityTrait;

class AdminPersonalityTraitController extends Controller
{
    /**
     * Display a listing of all personality traits.
     */
    public function index()
    {
        $personalityTraits = PersonalityTrait::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'personality_traits' => $personalityTraits,
            ],
        ]);
    }

    /**
     * Store a newly created personality trait.
     */
    public function store(StorePersonalityTraitRequest $request)
    {
        $personalityTrait = PersonalityTrait::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Personality trait created successfully.',
            'data' => [
                'personality_trait' => $personalityTrait,
            ],
        ], 201);
    }

    /**
     * Display the specified personality trait.
     */
    public function show(PersonalityTrait $personalityTrait)
    {
        $personalityTrait->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => [
                'personality_trait' => $personalityTrait,
            ],
        ]);
    }

    /**
     * Update the specified personality trait.
     */
    public function update(UpdatePersonalityTraitRequest $request, PersonalityTrait $personalityTrait)
    {
        $personalityTrait->update($request->validated());
        $personalityTrait->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Personality trait updated successfully.',
            'data' => [
                'personality_trait' => $personalityTrait,
            ],
        ]);
    }

    /**
     * Remove the specified personality trait.
     */
    public function destroy(PersonalityTrait $personalityTrait)
    {
        // Detach from all users first
        $personalityTrait->users()->detach();
        $personalityTrait->delete();

        return response()->json([
            'success' => true,
            'message' => 'Personality trait deleted successfully.',
        ]);
    }
}
