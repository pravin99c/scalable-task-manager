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

    $this->info("Marked {$expired} expired invitations; pruned {$deleted} invitations and {$challenges} email challenges.");
})->purpose('Expire invitations and prune old token metadata');

\Illuminate\Support\Facades\Schedule::command('invitations:prune')->dailyAt('03:10');
