<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ProjectInvitationMail extends Mailable
{
    public function __construct(
        public readonly string $projectName,
        public readonly string $role,
        public readonly string $token,
        public readonly string $expiresAt,
    ) {
    }

    public function build(): static
    {
        return $this->subject('Invitation to join TaskForge project: '.$this->projectName)
            ->text('emails.project-invitation');
    }
}
