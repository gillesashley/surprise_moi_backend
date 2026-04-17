<?php

namespace App\Listeners\Audit;

use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;

class LogPasswordReset
{
    public function __construct(private AuditService $audit) {}

    public function handle(PasswordReset $event): void
    {
        $this->audit->record('password_reset', $event->user, $event->user, retentionClass: 'critical');
    }
}
