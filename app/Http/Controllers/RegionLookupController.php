<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\JsonResponse;

class RegionLookupController extends Controller
{
    public function index(): JsonResponse
    {
        $regions = Region::with(['cities' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Region $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'cities' => $r->cities->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])->values(),
            ]);

        return response()->json(['data' => $regions]);
    }
}
