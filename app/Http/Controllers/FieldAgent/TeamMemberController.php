<?php

namespace App\Http\Controllers\FieldAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldAgent\StoreTeamMemberRequest;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function index(Request $request): Response
    {
        $members = $request->user()->teamMembers()
            ->select(['id', 'parent_user_id', 'name', 'email', 'phone', 'location', 'is_active', 'must_change_password', 'created_at'])
            ->addSelect([
                'vendors_onboarded' => VendorApplication::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('onboarded_by_user_id', 'users.id'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('field-agent/team/index', [
            'members' => $members,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('field-agent/team/new');
    }

    public function show(User $member): Response
    {
        Gate::authorize('view', $member);

        $vendors = VendorApplication::query()
            ->with('user:id,name,business_name')
            ->where('onboarded_by_user_id', $member->id)
            ->latest('created_at')
            ->get()
            ->map(fn (VendorApplication $app) => [
                'id' => $app->id,
                'business_name' => $app->user?->business_name ?: ($app->user?->name ?? ''),
                'status' => $app->status,
                'created_at' => $app->created_at?->toIso8601String(),
            ]);

        return Inertia::render('field-agent/team/show', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'location' => $member->location,
                'is_active' => (bool) $member->is_active,
                'must_change_password' => (bool) $member->must_change_password,
                'created_at' => $member->created_at?->toIso8601String(),
            ],
            'vendors' => $vendors,
        ]);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $member->update(['is_active' => $validated['is_active']]);

        return redirect("/field-agent/team/{$member->id}")
            ->with('success', $validated['is_active'] ? 'Member reactivated.' : 'Member deactivated.');
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $existing = User::where('email', $request->string('email')->lower())->first();

        if ($existing) {
            $existing->update([
                'name' => $request->string('name'),
                'phone' => $request->string('phone'),
                'location' => $request->string('location'),
                'role' => 'field_agent',
                'is_team_field_agent' => false,
                'parent_user_id' => $request->user()->id,
                'is_active' => true,
            ]);

            return redirect('/field-agent/team')
                ->with('success', 'Team member added successfully.');
        }

        User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email')->lower(),
            'phone' => $request->string('phone'),
            'location' => $request->string('location'),
            'role' => 'field_agent',
            'is_team_field_agent' => false,
            'parent_user_id' => $request->user()->id,
            'is_active' => true,
            'must_change_password' => false,
            'password' => Hash::make((string) $request->string('phone')),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        return redirect('/field-agent/team')
            ->with('success', 'Team member added. Their default password is their phone number.');
    }

    public function destroy(User $member): RedirectResponse
    {
        // Must authorize that the current user manages this member
        // Assuming Gate::authorize('update', $member) or similar is appropriate,
        // but checking parent_user_id is safest if the Gate is broad
        if ($member->parent_user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $hasVendors = VendorApplication::where('onboarded_by_user_id', $member->id)->exists();

        if ($hasVendors) {
            return back()->with('error', 'Cannot delete team member because they have already onboarded vendors.');
        }

        $member->delete();

        return redirect('/field-agent/team')->with('success', 'Team member deleted successfully.');
    }
}
