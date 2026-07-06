<?php

namespace GridX\Ai\Models;

use GridX\Casts\Json;
use GridX\Models\Model;
use GridX\Traits\HasApiModelBehavior;
use GridX\Traits\HasUuid;

class AiAdminAccessLog extends Model
{
    use HasUuid;
    use HasApiModelBehavior;

    protected $table = 'ai_admin_access_logs';

    protected $fillable = [
        'company_uuid',
        'ai_session_uuid',
        'ai_task_uuid',
        'viewed_by_uuid',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => Json::class,
    ];
}
