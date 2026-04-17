<?php

namespace App\Listeners\Audit;

use App\Services\AuditService;
use Illuminate\Auth\Events\Login;

class LogLogin
{
    public function __construct(private AuditService $audit) {}

    public function handle(Login $event): void
    {
        $this->audit->record('login', $event->user, $event->user, retentionClass: 'standard');
    }
}
