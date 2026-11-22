<?php

namespace App\Jobs;

use App\Mail\EmailOwnershipVerificationMail;
use App\Mail\ProjectInvitationMail;
use App\Models\EmailVerificationChallenge;
use App\Models\ProjectInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class DeliverTaskForgeMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;
    public array $backoff = [60, 300, 900, 1800];

    // Only an encrypted token goes into the durable jobs/failed_jobs payload.
    public function __construct(
        public readonly string $kind,
        public readonly int $recordId,
        public readonly string $encryptedToken,
    ) {
    }

    public function handle(): void
    {
        if ($this->kind === 'invitation') {
            $invite = ProjectInvitation::find($this->recordId);
            if (! $invite || ! $invite->isPending() || $invite->delivered_at !== null) {
                return;
            }

            $project = $invite->project;
            if (! $project) {
                return;
            }

            // Token never appears in app logs or database queue payloads in plaintext.
            Mail::to($invite->email)->send(new ProjectInvitationMail(
                $project->name,
                $invite->role,
                Crypt::decryptString($this->encryptedToken),
                $invite->expires_at->copy()->utc()->format('Y-m-d H:i:s'),
            ));
            ProjectInvitation::query()->whereKey($invite->getKey())
                ->whereNull('delivered_at')->update(['delivered_at' => now()]);
            return;
        }

        if ($this->kind === 'verification') {
            $challenge = EmailVerificationChallenge::find($this->recordId);
            if (! $challenge || ! $challenge->isPending() || $challenge->delivered_at !== null) {
                return;
            }

            $user = \App\Models\User::find($challenge->user_id);
            if (! $user || $user->email_verified_at !== null
                || strcasecmp($user->email, $challenge->email) !== 0) {
                return;
            }

            Mail::to($challenge->email)->send(new EmailOwnershipVerificationMail(
                Crypt::decryptString($this->encryptedToken),
                $challenge->expires_at->copy()->utc()->format('Y-m-d H:i:s'),
            ));
            EmailVerificationChallenge::query()->whereKey($challenge->getKey())
                ->whereNull('delivered_at')->update(['delivered_at' => now()]);
            return;
        }

        throw new \InvalidArgumentException('Unsupported mail delivery kind.');
    }
}
