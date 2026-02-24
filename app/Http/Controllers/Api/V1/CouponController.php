<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyCouponRequest;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CouponController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Coupon::query()->with('vendor');

        if ($request->user()->role === 'vendor') {
            $query->where('vendor_id', $request->user()->id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', '%'.$request->input('search').'%')
                    ->orWhere('title', 'like', '%'.$request->input('search').'%');
            });
        }

        $coupons = $query->latest()->paginate($request->input('per_page', 15));

        return CouponResource::collection($coupons);
    }

    public function store(StoreCouponRequest $request): CouponResource
    {
        $data = $request->validated();

        if ($request->user()->role === 'vendor') {
            $data['vendor_id'] = $request->user()->id;
        }

        $coupon = Coupon::create($data);

        return new CouponResource($coupon->load('vendor'));
    }

    public function show(Coupon $coupon): CouponResource
    {
        return new CouponResource($coupon->load('vendor'));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        $coupon->update($request->validated());

        return new CouponResource($coupon->fresh('vendor'));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        if ($coupon->used_count > 0) {
            return response()->json([
                'message' => 'Cannot delete a coupon that has been used.',
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::where('code', $request->input('code'))->firstOrFail();

        if (! $coupon->isValid()) {
            return response()->json([
                'message' => 'This coupon is no longer valid.',
            ], 422);
        }

        if (! $coupon->canBeUsedBy($request->user())) {
            return response()->json([
                'message' => 'You have reached the usage limit for this coupon.',
            ], 422);
        }

        $subtotal = $request->input('subtotal');
        $items = $request->input('items');

        if (! $this->isCouponApplicableToItems($coupon, $items)) {
            return response()->json([
                'message' => 'This coupon is not applicable to the selected items.',
            ], 422);
        }

        $discountAmount = $coupon->calculateDiscount($subtotal);

        if ($discountAmount === 0.0) {
            return response()->json([
                'message' => 'The minimum purchase amount for this coupon is '.$coupon->currency.' '.$coupon->min_purchase_amount,
            ], 422);
        }

        return response()->json([
            'coupon' => new CouponResource($coupon),
            'discount_amount' => $discountAmount,
            'subtotal' => $subtotal,
            'total' => max(0, $subtotal - $discountAmount),
            'currency' => $coupon->currency,
        ]);
    }

    public function available(Request $request): AnonymousResourceCollection
    {
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->latest()
            ->paginate($request->input('per_page', 15));

        return CouponResource::collection($coupons);
    }

    protected function isCouponApplicableToItems(Coupon $coupon, array $items): bool
    {
        if ($coupon->applicable_to === 'all') {
            return true;
        }

        $itemIds = collect($items)->pluck('id')->toArray();
        $itemTypes = collect($items)->pluck('type')->unique()->toArray();

        if ($coupon->applicable_to === 'products') {
            return in_array('product', $itemTypes);
        }

        if ($coupon->applicable_to === 'services') {
            return in_array('service', $itemTypes);
        }

        if ($coupon->applicable_to === 'specific' && ! empty($coupon->specific_ids)) {
            return ! empty(array_intersect($itemIds, $coupon->specific_ids));
        }

        return false;
    }
}
