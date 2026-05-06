<?php

namespace App\Policies;

use App\Models\User;

class TeamMemberPolicy
{
    /**
     * A lead may view a member iff that member's parent is the lead.
     */
    public function view(User $user, User $member): bool
    {
        return $this->isLead($user)
            && $member->parent_user_id === $user->id;
    }

    /**
     * Same constraint as view; only `is_active` is mutable here.
     */
    public function update(User $user, User $member): bool
    {
        return $this->view($user, $member);
    }

    private function isLead(User $user): bool
    {
        return $user->isFieldAgent()
            && (bool) $user->is_team_field_agent
            && $user->parent_user_id === null;
    }
}
