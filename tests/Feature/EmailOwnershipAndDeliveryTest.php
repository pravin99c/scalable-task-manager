<?php

namespace Tests\Feature;

use App\Jobs\DeliverTaskForgeMail;
use App\Mail\EmailOwnershipVerificationMail;
use App\Mail\ProjectInvitationMail;
use App\Models\EmailVerificationChallenge;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailOwnershipAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake([DeliverTaskForgeMail::class]);
    }

    private function as(User $user): static
    {
        Auth::forgetGuards();
        $this->actingAs($user);
        return $this;
    }

    private function queuedToken(string $kind, int $recordId): string
    {
        $job = null;
        Queue::assertPushed(DeliverTaskForgeMail::class, function (DeliverTaskForgeMail $queued) use ($kind, $recordId, &$job) {
            if ($queued->kind === $kind && $queued->recordId === $recordId) {
                $job = $queued;
                return true;
            }
            return false;
        });

        $this->assertNotNull($job);

        return Crypt::decryptString($job->encryptedToken);
    }

    public function test_registration_creates_unverified_account_and_queues_email_proof(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Account',
            'email' => 'new@example.test',
            'password' => 'a-strong-unique-password',
            'password_confirmation' => 'a-strong-unique-password',
        ])->assertCreated()->assertJsonPath('data.user.email_verified_at', null);

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $challenge = EmailVerificationChallenge::where('user_id', $user->id)->firstOrFail();
        $token = $this->queuedToken('verification', $challenge->id);
        $this->assertSame(hash('sha256', $token), $challenge->token_hash);
        $this->assertNotSame($token, $challenge->token_hash);

        $job = null;
        Queue::assertPushed(DeliverTaskForgeMail::class, function ($queued) use ($challenge, &$job) {
            if ($queued->kind === 'verification' && $queued->recordId === $challenge->id) {
                $job = $queued;
                return true;
            }
            return false;
        });
        $job->handle();
        Mail::assertSent(EmailOwnershipVerificationMail::class, fn ($mail) =>
            $mail->hasTo($user->email) && $mail->token === $token);

        $this->as($user)->postJson('/api/v2/auth/email/verify', ['token' => $token])
            ->assertOk()->assertJsonPath('data.email_verified', true);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->as($user)->postJson('/api/v2/auth/email/verify', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_wrong_account_and_expired_verification_are_denied(): void
    {
        $victim = User::factory()->unverified()->create();
        $attacker = User::factory()->unverified()->create();
        $this->as($victim)->postJson('/api/v2/auth/email/verification/send')->assertStatus(202);

        $challenge = EmailVerificationChallenge::where('user_id', $victim->id)->firstOrFail();
        $token = $this->queuedToken('verification', $challenge->id);
        $this->as($attacker)->postJson('/api/v2/auth/email/verify', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        $challenge->update(['expires_at' => now()->subSecond()]);
        $this->as($victim)->postJson('/api/v2/auth/email/verify', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertNull($victim->fresh()->email_verified_at);
    }

    public function test_unverified_invitee_cannot_accept_until_email_verified(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->unverified()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $invitee->email, 'role' => 'editor',
        ])->assertCreated();

        $invitation = ProjectInvitation::where('project_id', $project->id)->firstOrFail();
        $token = $this->queuedToken('invitation', $invitation->id);
        $this->as($invitee)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertForbidden();

        $this->as($invitee)->postJson('/api/v2/auth/email/verification/send')->assertStatus(202);
        $challenge = EmailVerificationChallenge::where('user_id', $invitee->id)->firstOrFail();
        $proof = $this->queuedToken('verification', $challenge->id);
        $this->as($invitee)->postJson('/api/v2/auth/email/verify', ['token' => $proof])->assertOk();
        // Feature tests reuse the model object; a real HTTP request reloads it.
        $invitee->refresh();

        $this->as($invitee)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertOk()->assertJsonPath('data.role', 'editor');
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $invitee->id]);
    }

    public function test_direct_membership_cannot_bypass_email_verification(): void
    {
        $owner = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->as($owner)->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $unverified->id, 'role' => 'editor',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $unverified->id]);
    }

    public function test_changed_account_email_resets_verification(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->email_verified_at);
        $user->update(['email' => 'changed@example.test']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_replaced_invitation_jobs_do_not_send_revoked_tokens(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $invitee->email, 'role' => 'editor',
        ])->assertCreated();
        $old = ProjectInvitation::firstOrFail();
        $oldJob = null;
        Queue::assertPushed(DeliverTaskForgeMail::class, function ($job) use ($old, &$oldJob) {
            if ($job->kind === 'invitation' && $job->recordId === $old->id) {
                $oldJob = $job; return true;
            }
            return false;
        });

        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $invitee->email, 'role' => 'viewer',
        ])->assertCreated();
        $this->assertNotNull($old->fresh()->revoked_at);
        $oldJob->handle();
        Mail::assertNothingSent();
    }

    public function test_invitation_abuse_is_limited_per_recipient_over_24_hours(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        for ($i = 0; $i < 3; $i++) {
            $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
                'email' => $invitee->email, 'role' => 'viewer',
            ])->assertCreated();
        }
        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $invitee->email, 'role' => 'editor',
        ])->assertStatus(429);
        $this->assertDatabaseCount('project_invitations', 3);
    }

    public function test_cleanup_marks_expired_and_prunes_old_records(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $recent = ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $owner->id,
            'email' => 'recent@example.test',
            'role' => 'viewer',
            'token_hash' => hash('sha256', 'recent'),
            'expires_at' => now()->subDay(),
        ]);
        $old = ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $owner->id,
            'email' => 'old@example.test',
            'role' => 'editor',
            'token_hash' => hash('sha256', 'old'),
            'expires_at' => now()->subDays(40),
            'revoked_at' => now()->subDays(40),
        ]);

        $this->artisan('invitations:prune')->assertExitCode(0);
        $this->assertNotNull($recent->fresh()->revoked_at);
        $this->assertDatabaseMissing('project_invitations', ['id' => $old->id]);
        $this->assertDatabaseHas('project_invitations', ['id' => $recent->id]);
    }
}
