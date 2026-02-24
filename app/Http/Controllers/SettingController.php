<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
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

        return Inertia::render('settings/index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('updateAny', Setting::class);

        $validated = $request->validate([
            'vendor_onboarding_fee' => 'required|numeric|min:0',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value, 'number');
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
