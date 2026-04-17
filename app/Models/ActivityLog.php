<?php

namespace App\Models;

use App\Traits\PreventModification;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    use PreventModification;

    protected $table = 'activity_log';
}
