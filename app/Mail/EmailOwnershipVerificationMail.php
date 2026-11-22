<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class EmailOwnershipVerificationMail extends Mailable
{
    public function __construct(
        public readonly string $token,
        public readonly string $expiresAt,
    ) {
    }

    public function build(): static
    {
        return $this->subject('Verify your TaskForge email address')
            ->text('emails.email-ownership-verification');
    }
}
