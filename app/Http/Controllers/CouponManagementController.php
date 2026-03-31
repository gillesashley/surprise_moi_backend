<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CouponManagementController extends Controller
{
    public function index()
    {
        $coupons = Coupon::query()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('coupons/index', [
            'coupons' => $coupons,
        ]);
    }

    public function create()
    {
        if (! Auth::user()->isAdmin()) {
            return back()->with('error', 'Only admins can create coupons.');
        }

        return Inertia::render('coupons/create');
    }

    public function store(Request $request)
    {
        if (! Auth::user()->isAdmin()) {
            return back()->with('error', 'Only admins can create coupons.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:coupons,code'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:percentage,fixed,cashback'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_limit_per_user' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date', 'before_or_equal:valid_until'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'applicable_to' => ['required', 'in:all,products,services'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        Coupon::create($validated);

        return redirect()->route('dashboard.coupons.index')->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        return Inertia::render('coupons/edit', [
            'coupon' => $coupon,
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons')->ignore($coupon->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:percentage,fixed,cashback'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_limit_per_user' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date', 'before_or_equal:valid_until'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'applicable_to' => ['required', 'in:all,products,services'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        $coupon->update($validated);

        return redirect()->route('dashboard.coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        if (! Auth::user()->isAdmin()) {
            return back()->with('error', 'Only admins can delete coupons.');
        }

        if ($coupon->used_count > 0) {
            return back()->with('error', 'Cannot delete a coupon that has been used.');
        }

        $coupon->delete();

        return redirect()->route('dashboard.coupons.index')->with('success', 'Coupon deleted successfully.');
    }
}
