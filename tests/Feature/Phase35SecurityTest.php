<?php

namespace Tests\Feature;

use App\Jobs\DeliverTaskForgeMail;
use App\Mail\EmailOwnershipVerificationMail;
use App\Models\EmailVerificationChallenge;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase35SecurityTest extends TestCase
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

    public function test_recipient_limit_is_shared_across_multiple_project_owners(): void
    {
        $recipient = User::factory()->create();
        $owners = User::factory()->count(4)->create();
        foreach ($owners as $index => $owner) {
            $project = Project::factory()->create(['created_by' => $owner->id]);
            $request = $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
                'email' => strtoupper($recipient->email), 'role' => 'viewer',
            ]);
            $request->assertStatus($index < 3 ? 201 : 429);
        }

        $this->assertDatabaseCount('project_invitations', 3);
        $this->assertDatabaseHas('invitation_rate_limit_locks', [
            'key_hash' => hash('sha256', 'recipient:'.strtolower($recipient->email)),
        ]);
    }

    public function test_changing_email_revokes_tokens_sessions_membership_and_old_invites(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create(['email' => 'previous@example.test']);
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($user->id, ['role' => 'editor']);
        $task = Task::factory()->create([
            'created_by' => $owner->id,
            'project_id' => $project->id,
            'assigned_to' => $user->id,
        ]);
        $bearer = $user->createToken('prior-device')->plainTextToken;

        DB::table('sessions')->insert([
            'id' => 'session-from-old-email',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => time(),
        ]);
        $invite = ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $owner->id,
            'email' => $user->email,
            'role' => 'viewer',
            'token_hash' => hash('sha256', 'pending-old-email'),
            'expires_at' => now()->addDays(2),
        ]);
        $issued = ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $user->id,
            'email' => 'somebody@example.test',
            'role' => 'viewer',
            'token_hash' => hash('sha256', 'issued-old-email'),
            'expires_at' => now()->addDays(2),
        ]);

        $this->as($user)->patchJson('/api/v2/auth/email', [
            'email' => 'new@example.test',
            'current_password' => 'password',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('new@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertSame(1, $fresh->session_version);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-from-old-email']);
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => null]);
        $this->assertNotNull($invite->fresh()->revoked_at);
        $this->assertNotNull($issued->fresh()->revoked_at);
        $this->assertDatabaseHas('email_verification_challenges', ['user_id' => $user->id, 'email' => 'new@example.test']);
        Auth::forgetGuards();
        $this->getJson('/api/v1/user', ['Authorization' => 'Bearer '.$bearer])->assertUnauthorized();
    }

    public function test_email_change_requires_valid_password(): void
    {
        $user = User::factory()->create();
        $this->as($user)->patchJson('/api/v2/auth/email', [
            'email' => 'changed@example.test',
            'current_password' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('email_verification_challenges', 0);
    }

    public function test_legacy_admin_style_email_update_also_revokes_prior_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($member->id, ['role' => 'editor']);
        $token = $member->createToken('legacy-admin-client');

        DB::transaction(fn () => $member->update(['email' => 'new-legacy@example.test']));

        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $member->id]);
        $this->assertNull($member->fresh()->email_verified_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_mail_delivery_receipt_suppresses_repeat_job_execution(): void
    {
        $user = User::factory()->unverified()->create();
        $this->as($user)->postJson('/api/v2/auth/email/verification/send')->assertStatus(202);
        $challenge = EmailVerificationChallenge::where('user_id', $user->id)->firstOrFail();
        $job = null;
        Queue::assertPushed(DeliverTaskForgeMail::class, function ($candidate) use ($challenge, &$job) {
            if ($candidate->kind === 'verification' && $candidate->recordId === $challenge->id) {
                $job = $candidate;
                return true;
            }
            return false;
        });

        $this->assertNotNull($job);
        $job->handle();
        $this->assertNotNull($challenge->fresh()->delivered_at);
        $job->handle();
        Mail::assertSent(EmailOwnershipVerificationMail::class, 1);
    }

    public function test_operational_command_requires_prune_heartbeat_and_detects_failed_jobs(): void
    {
        $this->artisan('taskforge:operations-check')->assertExitCode(1);
        $this->artisan('invitations:prune')->assertExitCode(0);
        $this->artisan('taskforge:operations-check')->assertExitCode(0);
        DB::table('failed_jobs')->insert([
            'uuid' => 'simulated-failed-job',
            'connection' => 'database',
            'queue' => 'mail',
            'payload' => '{}',
            'exception' => 'Synthetic failure only',
            'failed_at' => now(),
        ]);
        $this->artisan('taskforge:operations-check')->assertExitCode(1);
    }
}
