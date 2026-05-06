<?php

namespace App\Http\Controllers\FieldAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldAgent\StoreTeamMemberRequest;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(\Illuminate\Http\Request $request, User $member): Response
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $member);

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

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email')->lower(),
            'phone' => $request->string('phone'),
            'location' => $request->string('location'),
            'role' => 'field_agent',
            'is_team_field_agent' => false,
            'parent_user_id' => $request->user()->id,
            'is_active' => true,
            'must_change_password' => true,
            'password' => Hash::make((string) $request->string('phone')),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        return redirect('/field-agent/team')
            ->with('success', 'Team member added. Their default password is their phone number.');
    }
}
