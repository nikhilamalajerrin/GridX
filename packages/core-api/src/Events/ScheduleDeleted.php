<?php

namespace GridX\Events;

use GridX\Models\Schedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class ScheduleDeleted
{
    use InteractsWithSockets;
    use SerializesModels;

    public $schedule;

    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }
}
