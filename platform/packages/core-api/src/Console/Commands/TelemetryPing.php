<?php

namespace GridX\Console\Commands;

use GridX\Support\Telemetry;
use Illuminate\Console\Command;

class TelemetryPing extends Command
{
    protected $signature   = 'telemetry:ping';
    protected $description = 'Send periodic GridX telemetry ping';

    public function handle()
    {
        $this->info('Sending telemetry...');
        $sent = Telemetry::send();
        $sent ? $this->info('Telemetry sent.') : $this->error('Telemetry failed to send, check logs for details...');
    }
}
