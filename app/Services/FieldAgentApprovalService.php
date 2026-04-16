<?php

namespace App\Services;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\FieldAgentApplication;
use App\Models\User;
use App\Notifications\FieldAgentApprovedNotification;
use App\Notifications\FieldAgentRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class FieldAgentApprovalService
{
    public function approve(FieldAgentApplication $application, User $admin): User
    {
        if (! $application->canBeReviewed()) {
            throw new RuntimeException('Application cannot be reviewed in its current state.');
        }

        return DB::transaction(function () use ($application, $admin) {
            $user = User::create([
                'name' => $application->fullName(),
                'email' => $application->email,
                'password' => $application->password,
                'role' => 'field_agent',
                'phone' => $application->contact_number,
                'email_verified_at' => now(),
            ]);

            $application->update([
                'status' => FieldAgentApplicationStatus::Approved->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'approved_user_id' => $user->id,
                'password' => null,
                'rejection_reason' => null,
            ]);

            Notification::send($application->fresh(), new FieldAgentApprovedNotification($application->fresh()));

            return $user;
        });
    }

    public function reject(FieldAgentApplication $application, User $admin, string $reason): void
    {
        if (! $application->canBeReviewed()) {
            throw new RuntimeException('Application cannot be reviewed in its current state.');
        }

        DB::transaction(function () use ($application, $admin, $reason) {
            $application->update([
                'status' => FieldAgentApplicationStatus::Rejected->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            Notification::send($application->fresh(), new FieldAgentRejectedNotification($application->fresh()));
        });
    }
}
