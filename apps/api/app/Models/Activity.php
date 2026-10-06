<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * One audit-log row (config/activitylog.php). Its own class so the admin
 * panel resolves App\Policies\ActivityPolicy for it, and nothing else.
 */
class Activity extends SpatieActivity {}
