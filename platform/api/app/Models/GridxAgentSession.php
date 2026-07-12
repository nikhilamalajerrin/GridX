<?php

namespace App\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;

class GridxAgentSession extends Model
{
    use HasUuid;
    use HasPublicId;

    protected $table = 'gridx_agent_sessions';
    protected $publicIdType = 'agentsession';

    protected $guarded = [];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function quotes()
    {
        return $this->hasMany(GridxQuote::class, 'agent_session_uuid', 'uuid');
    }
}
