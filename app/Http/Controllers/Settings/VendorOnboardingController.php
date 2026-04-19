<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorOnboardingController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::all()->mapWithKeys(function ($setting) {
            return [$setting->key => [
                'value' => $setting->value,
                'type' => $setting->type,
                'description' => $setting->description,
            ]];
        });

        return Inertia::render('settings/vendor-onboarding', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('updateAny', Setting::class);

        $validated = $request->validate([
            'vendor_tier1_onboarding_fee' => 'required|numeric|min:0',
            'vendor_tier2_onboarding_fee' => 'required|numeric|min:0',
            'vendor_tier1_commission_rate' => 'required|numeric|min:0|max:100',
            'vendor_tier2_commission_rate' => 'required|numeric|min:0|max:100',
            'referral_bonus_customer_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_vendor_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_influencer_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_field_agent_pct' => 'required|numeric|min:0|max:100',
            'referral_bonus_employee_pct' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value, 'number');
        }

        app(\App\Services\AuditService::class)->record(
            'settings.updated',
            null,
            $request->user(),
            extra: ['section' => 'vendor_onboarding', 'keys' => array_keys($validated)],
            retentionClass: 'critical'
        );

        return back()->with('success', 'Settings updated successfully.');
    }
}
