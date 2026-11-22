<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailChangeService
{
    /**
     * All account identity changes use one database transaction. The User
     * model's updated event also removes previously granted email-bound
     * access when an administrator changes the email address.
     */
    public function change(User $user, string $address): void
    {
        DB::transaction(function () use ($user, $address) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $new = Str::lower(trim($address));

            if (strcasecmp($locked->email, $new) === 0) {
                return;
            }

            $locked->forceFill(['email' => $new])->save();
            // The new mailbox must be verified; enqueue a disposable,
            // hashed ownership challenge before this transaction commits.
            app(EmailOwnershipService::class)->issue($locked);
        }, 3);
    }
}
