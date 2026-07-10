<?php

namespace GridX\Console\Commands;

use GridX\Support\SocketCluster\SocketClusterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyInstalled extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gridx:notify-installed {--channel=gridx.install}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify open install pages that GridX setup has completed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $channel = $this->option('channel') ?: 'gridx.install';
        $payload = [
            'event'     => 'gridx.installed',
            'installed' => true,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $socketClusterClient = new SocketClusterService();
            $sent                = $socketClusterClient->send($channel, $payload);

            if (!$sent) {
                $message = $socketClusterClient->error() ?: 'SocketCluster did not acknowledge the install notification.';
                $this->warn('Install notification was not sent: ' . $message);
                Log::warning('GridX install notification was not sent.', [
                    'channel' => $channel,
                    'error'   => $message,
                ]);

                return 0;
            }

            $this->info('Install notification sent.');
        } catch (\Throwable $e) {
            $this->warn('Install notification failed: ' . $e->getMessage());
            Log::warning('GridX install notification failed.', [
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);
        }

        return 0;
    }
}
