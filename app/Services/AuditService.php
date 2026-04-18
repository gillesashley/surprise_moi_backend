<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function record(
        string $event,
        ?Model $subject = null,
        ?Model $causer = null,
        array $extra = [],
        string $retentionClass = 'standard'
    ): ActivityLog {
        return ActivityLog::create([
            'log_name' => 'default',
            'description' => $event,
            'event' => $event,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer ? $causer->getMorphClass() : null,
            'causer_id' => $causer?->getKey(),
            'properties' => array_merge(
                AuditContext::toArray(),
                ['retention_class' => $retentionClass],
                $extra ? ['extra' => $extra] : []
            ),
        ]);
    }
}
