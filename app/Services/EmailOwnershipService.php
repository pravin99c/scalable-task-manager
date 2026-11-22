<?php

namespace App\Services;

use App\Jobs\DeliverTaskForgeMail;
use App\Models\EmailVerificationChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailOwnershipService
{
    /**
     * Call inside an existing DB transaction. The database-backed queue job
     * is inserted in the SAME transaction as the challenge. A rollback removes
     * both; the worker cannot read it before commit.
     */
    public function issue(User $user): EmailVerificationChallenge
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['This email is already verified.'],
            ]);
        }

        EmailVerificationChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $token = Str::random(80);
        $challenge = EmailVerificationChallenge::create([
            'user_id' => $user->id,
            'email' => Str::lower($user->email),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(30),
        ]);

        DeliverTaskForgeMail::dispatch(
            'verification',
            $challenge->id,
            Crypt::encryptString($token),
        )->onConnection('database')->onQueue('mail')->beforeCommit();

        return $challenge;
    }

    public function verify(User $user, string $token): void
    {
        DB::transaction(function () use ($user, $token) {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $challenge = EmailVerificationChallenge::query()
                ->where('user_id', $user->getKey())
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $challenge || ! $challenge->isPending()
                || ! hash_equals($challenge->email, Str::lower($user->email))
                || $user->email_verified_at !== null) {
                throw ValidationException::withMessages([
                    'token' => ['Verification token is invalid or expired.'],
                ]);
            }

            $user->forceFill(['email_verified_at' => now()])->save();
            EmailVerificationChallenge::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        });
    }
}
