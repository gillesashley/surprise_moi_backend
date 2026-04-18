<?php

namespace App\Listeners\Audit;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;

class LogLoginFailed
{
    public function __construct(private AuditService $audit) {}

    public function handle(Failed $event): void
    {
        $this->audit->record(
            'login_failed',
            subject: null,
            causer: null,
            extra: ['email_attempted' => $event->credentials['email'] ?? null],
            retentionClass: 'standard'
        );
    }
}
