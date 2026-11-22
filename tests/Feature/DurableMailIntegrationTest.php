<?php

namespace Tests\Feature;

use App\Jobs\DeliverTaskForgeMail;
use App\Mail\EmailOwnershipVerificationMail;
use App\Mail\ProjectInvitationMail;
use App\Models\EmailVerificationChallenge;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Services\EmailOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DurableMailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Unlike Queue::fake, this writes REAL serialized database queue jobs.
        config(['queue.default' => 'database', 'queue.connections.database.connection' => null]);
        Mail::fake();
    }

    private function as(User $user): static
    {
        Auth::forgetGuards();
        $this->actingAs($user);

        return $this;
    }

    public function test_verification_job_is_durable_encrypted_and_delivered_by_real_queue_pop(): void
    {
        $user = User::factory()->unverified()->create();
        $this->as($user)->postJson('/api/v2/auth/email/verification/send')->assertStatus(202);

        $challenge = EmailVerificationChallenge::where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseCount('jobs', 1);

        $rawPayload = (string) DB::table('jobs')->value('payload');
        $this->assertStringNotContainsString($challenge->token_hash, $rawPayload);
        $this->assertStringNotContainsString($user->email, $rawPayload);

        $job = Queue::connection('database')->pop('mail');
        $this->assertNotNull($job);
        $this->assertSame(DeliverTaskForgeMail::class, $job->payload()['displayName']);

        $job->fire();
        $job->delete();

        Mail::assertSent(EmailOwnershipVerificationMail::class, function ($mail) use ($user, $challenge) {
            return $mail->hasTo($user->email)
                && hash('sha256', $mail->token) === $challenge->token_hash;
        });
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_invitation_is_sent_only_after_committed_record_and_revocation_skips_delivery(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $recipient->email, 'role' => 'viewer',
        ])->assertCreated();

        $record = ProjectInvitation::firstOrFail();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertStringNotContainsString($record->token_hash, (string) DB::table('jobs')->value('payload'));

        $this->as($owner)->deleteJson("/api/v2/projects/{$project->id}/invitations/{$record->id}")
            ->assertOk();

        $job = Queue::connection('database')->pop('mail');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_rollback_removes_challenge_and_email_queue_job_atomically(): void
    {
        $user = User::factory()->unverified()->create();

        try {
            DB::transaction(function () use ($user) {
                app(EmailOwnershipService::class)->issue($user);
                $this->assertDatabaseCount('email_verification_challenges', 1);
                $this->assertDatabaseCount('jobs', 1);

                throw new RuntimeException('Deliberate rollback');
            });
            $this->fail('Transaction did not throw');
        } catch (RuntimeException $e) {
            $this->assertSame('Deliberate rollback', $e->getMessage());
        }

        $this->assertDatabaseCount('email_verification_challenges', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_failed_mail_transport_throws_for_worker_retry_instead_of_consuming_token(): void
    {
        $recipient = User::factory()->unverified()->create();
        $this->as($recipient)->postJson('/api/v2/auth/email/verification/send')->assertStatus(202);

        $challenge = EmailVerificationChallenge::firstOrFail();
        $job = new DeliverTaskForgeMail('verification', $challenge->id, Crypt::encryptString('dummy-secret-token'));

        Mail::shouldReceive('to')->once()->with($recipient->email)
            ->andThrow(new RuntimeException('Simulated mail transport outage'));

        try {
            $job->handle();
            $this->fail('Mail transport exception was unexpectedly swallowed');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated mail transport outage', $e->getMessage());
        }

        $this->assertSame(5, $job->tries);
        $this->assertSame([60, 300, 900, 1800], $job->backoff);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_expired_email_challenge_is_not_delivered_to_mailer(): void
    {
        $user = User::factory()->unverified()->create();
        $this->as($user)->postJson('/api/v2/auth/email/verification/send')->assertStatus(202);

        EmailVerificationChallenge::firstOrFail()->update(['expires_at' => now()->subSecond()]);

        $job = Queue::connection('database')->pop('mail');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();

        Mail::assertNothingSent();
    }

    public function test_invitation_cannot_be_used_for_different_verified_account(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $imposter = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $recipient->email, 'role' => 'editor',
        ])->assertCreated();

        $queued = Queue::connection('database')->pop('mail');
        $this->assertNotNull($queued);
        $queued->fire();
        $queued->delete();

        $token = null;
        Mail::assertSent(ProjectInvitationMail::class, function (ProjectInvitationMail $message) use ($recipient, &$token) {
            if (! $message->hasTo($recipient->email)) {
                return false;
            }
            $token = $message->token;
            return true;
        });

        // Exercise a VALID token under the wrong identity, not just gibberish.
        $this->assertNotNull($token);
        $this->as($imposter)->postJson('/api/v2/invitations/accept', [
            'token' => $token,
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $imposter->id]);
        $this->as($recipient)->postJson('/api/v2/invitations/accept', [
            'token' => $token,
        ])->assertOk()->assertJsonPath('data.role', 'editor');
    }
}
