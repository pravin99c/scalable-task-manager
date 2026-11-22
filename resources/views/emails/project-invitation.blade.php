You have been invited to join the TaskForge project "{{ $projectName }}" as a {{ $role }}.

This invitation is for the email address at which you received this message.
Sign in to TaskForge using the account that controls this mailbox. If the account email is not yet verified, request an email verification code through POST /api/v2/auth/email/verification/send and confirm it with POST /api/v2/auth/email/verify before accepting this invitation.

Use the invitation token below with a POST request to /api/v2/invitations/accept:

{{ $token }}

The invitation expires at {{ $expiresAt }} (UTC).

Do not share this token. It is single-use; after acceptance it cannot be used again.
If you did not expect this invitation, you can ignore this email.
