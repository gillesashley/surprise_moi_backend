<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorVisit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Storage;

class VendorVisitsController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorVisit::with(['vendorApplication.user', 'fieldAgent'])
            ->latest('submitted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $visits = $query->paginate(15);

        return Inertia::render('admin/vendor-visits/index', [
            'visits' => $visits->through(fn ($visit) => [
                'id' => $visit->id,
                'vendor_name' => $visit->vendorApplication?->user?->name ?? 'Unknown',
                'field_agent_name' => $visit->fieldAgent?->name ?? 'Unknown',
                'status' => $visit->status,
                'submitted_at' => $visit->submitted_at?->toIso8601String(),
                'has_shop' => $visit->has_shop,
                'location' => $visit->has_shop ? $visit->shop_location : $visit->primary_business_address,
            ]),
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(VendorVisit $visit)
    {
        $visit->load(['vendorApplication.user', 'vendorApplication.bespokeServices', 'fieldAgent', 'overrideBy']);

        return Inertia::render('admin/vendor-visits/show', [
            'visit' => [
                'id' => $visit->id,
                'status' => $visit->status,
                'submitted_at' => $visit->submitted_at?->toIso8601String(),
                'ghana_card_number' => $visit->ghana_card_number,
                'tin_number' => $visit->tin_number,
                'has_shop' => $visit->has_shop,
                'shop_location' => $visit->shop_location,
                'primary_business_address' => $visit->primary_business_address,
                'storefront_photo' => $visit->storefront_photo_path ? Storage::url($visit->storefront_photo_path) : null,
                'owner_photo' => $visit->owner_photo_path ? Storage::url($visit->owner_photo_path) : null,
                'field_agent' => [
                    'name' => $visit->fieldAgent?->name ?? 'Unknown',
                ],
                'application' => $visit->vendorApplication ? [
                    'id' => $visit->vendorApplication->id,
                    'status' => $visit->vendorApplication->status,
                    'user' => [
                        'name' => $visit->vendorApplication->user?->name ?? 'Unknown',
                        'email' => $visit->vendorApplication->user?->email ?? 'Unknown',
                        'phone' => $visit->vendorApplication->user?->phone,
                    ],
                    'is_registered_vendor' => $visit->vendorApplication->isRegisteredVendor(),
                    'ghana_card_front' => $visit->vendorApplication->ghana_card_front ? Storage::url($visit->vendorApplication->ghana_card_front) : null,
                    'ghana_card_back' => $visit->vendorApplication->ghana_card_back ? Storage::url($visit->vendorApplication->ghana_card_back) : null,
                ] : null,
            ],
        ]);
    }
}
