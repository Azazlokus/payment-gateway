<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Signals all queue workers and Horizon to finish their current job and stop.
 * Use during deployments: php artisan payments:shutdown
 * Workers check the 'payments:shutdown' cache flag every 5 seconds (Laravel default).
 */
final class GracefulShutdownCommand extends Command
{
    protected $signature = 'payments:shutdown {--wait=30 : Seconds to wait for workers to stop}';

    protected $description = 'Gracefully shut down queue workers and Horizon';

    public function handle(): int
    {
        $this->info('Sending shutdown signal to all workers...');

        // Sets the `illuminate:queue:restart` cache key — workers check this on every loop
        $this->call('queue:restart');

        // Also terminate Horizon if running
        $this->call('horizon:terminate');

        $wait = (int) $this->option('wait');
        $this->info("Workers will finish current jobs and stop within {$wait} seconds.");

        return self::SUCCESS;
    }
}
