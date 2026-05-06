<?php

namespace App\Http\Controllers\FieldAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldAgent\StoreTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class TeamMemberController extends Controller
{
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
