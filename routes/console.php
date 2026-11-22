<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Mark outstanding invitations as expired and remove old settled records.
// Safe for repeated runs; do not remove pending non-expired invitations.
Artisan::command('invitations:prune', function () {
    $now = now();
    $expired = \App\Models\ProjectInvitation::query()
        ->whereNull('accepted_at')->whereNull('revoked_at')
        ->where('expires_at', '<=', $now)
        ->update(['revoked_at' => $now]);

    $before = $now->copy()->subDays(30);
    $deleted = \App\Models\ProjectInvitation::query()
        ->where('expires_at', '<', $before)
        ->where(function ($query) {
            $query->whereNotNull('accepted_at')->orWhereNotNull('revoked_at');
        })->delete();

    $challenges = \App\Models\EmailVerificationChallenge::query()
        ->where('expires_at', '<', $before)->delete();

    \Illuminate\Support\Facades\Cache::put('taskforge:prune_last_run', now()->toIso8601String(), now()->addDays(2));
    $this->info("Marked {$expired} expired invitations; pruned {$deleted} invitations and {$challenges} email challenges.");
})->purpose('Expire invitations and prune old token metadata');

\Illuminate\Support\Facades\Schedule::command('invitations:prune')->dailyAt('03:10');


// Operational checks are non-mutating. Run on the same database as the mail worker.
Artisan::command('taskforge:operations-check', function () {
    $queue = \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'mail');
    $backlogged = (clone $queue)->where('created_at', '<', now()->subMinutes(10)->getTimestamp())->count();
    $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
    $schedulerBeat = \Illuminate\Support\Facades\Cache::get('taskforge:prune_last_run');

    $this->line("Old mail jobs: {$backlogged}; failed jobs: {$failed}");
    $this->line($schedulerBeat ? 'Scheduler has recorded a prune run.' : 'Scheduler has no recorded prune run.');

    return $backlogged > 0 || $failed > 0 || ! $schedulerBeat ? 1 : 0;
})->purpose('Check mail queue backlog, failed jobs and scheduler heartbeat');

