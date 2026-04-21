<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorFieldVerificationResource;
use Illuminate\Http\Request;

class VendorFieldVerificationController extends Controller
{
    /**
     * Get the authenticated vendor's field verification status and visit history.
     */
    public function show(Request $request)
    {
        return new VendorFieldVerificationResource($request->user());
    }
}
