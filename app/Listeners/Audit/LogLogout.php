<?php

namespace App\Listeners\Audit;

use App\Services\AuditService;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function __construct(private AuditService $audit) {}

    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        $this->audit->record('logout', $event->user, $event->user, retentionClass: 'standard');
    }
}
