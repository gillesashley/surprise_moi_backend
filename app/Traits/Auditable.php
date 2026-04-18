<?php

namespace App\Traits;

use App\Support\AuditContext;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait Auditable
{
    use LogsActivity;

    /**
     * Sensitive fields redacted across the whole project. Individual models
     * can add more via a $auditableRedactExtra property.
     *
     * @var array<int, string>
     */
    protected array $auditableRedactDefault = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'otp',
        'otp_expires_at',
        'paystack_recipient_code',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        $redact = array_values(array_unique(array_merge(
            $this->auditableRedactDefault,
            property_exists($this, 'auditableRedactExtra') ? $this->auditableRedactExtra : []
        )));

        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logExcept($redact)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $props = $activity->properties ?? collect();
        if (! is_object($props) || ! method_exists($props, 'merge')) {
            $props = collect($props);
        }

        $activity->properties = $props->merge(array_merge(
            AuditContext::toArray(),
            ['retention_class' => $this->retentionClass($eventName)]
        ));
    }

    /**
     * Retention class for a given event. Override per model to return
     * 'critical' for deletes/approvals/etc.
     */
    public function retentionClass(string $eventName): string
    {
        return 'standard';
    }
}
