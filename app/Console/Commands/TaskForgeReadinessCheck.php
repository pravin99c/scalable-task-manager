<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Private CLI readiness checks. No public endpoint, writes, queue dispatch,
 * emails, migrations, credentials, or network requests outside configured
 * read-only DB/cache clients.
 */
class TaskForgeReadinessCheck extends Command
{
    protected $signature = 'taskforge:readiness-check';

    protected $description = 'Check application dependencies before controlled release (read only)';

    public function handle(): int
    {
        $failed = false;

        try {
            DB::select('SELECT 1 AS ready');
            $this->line('PASS: database connection responded to a read-only query.');
        } catch (Throwable $exception) {
            $failed = true;
            $this->error('FAIL: database read-only connectivity check failed.');
        }

        try {
            // Reading a deliberately absent, namespaced key checks cache
            // connectivity on Redis/database stores without modifying data.
            Cache::get('taskforge:readiness:probe:nonexistent');
            $this->line('PASS: configured cache store responded to a read.');
        } catch (Throwable $exception) {
            $failed = true;
            $this->error('FAIL: cache read-only connectivity check failed.');
        }

        $queue = (string) config('queue.default');
        if (app()->environment('production') && in_array($queue, ['sync', 'null'], true)) {
            $failed = true;
            $this->error('FAIL: production requires a persistent queue connection.');
        } else {
            $this->line('PASS: queue configuration is compatible with this environment.');
        }

        if (config('app.debug') && app()->environment('production')) {
            $failed = true;
            $this->error('FAIL: production debug mode must be disabled.');
        } else {
            $this->line('PASS: debug mode policy is compatible with this environment.');
        }

        $this->line('Note: readiness is not proof of scheduler, worker, backup, mail or HTTPS health.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
