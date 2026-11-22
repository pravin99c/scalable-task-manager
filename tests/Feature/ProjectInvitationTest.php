<?php

namespace Tests\Feature;

use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Crypt;
use App\Jobs\DeliverTaskForgeMail;
use Tests\TestCase;

class ProjectInvitationTest extends TestCase
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

    private function invite(Project $project, User $owner, string $email, string $role = 'editor'): string
    {
        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $email,
            'role' => $role,
        ])->assertCreated()->assertJsonMissing(['token_hash' => 'anything']);

        $latest = ProjectInvitation::query()->where('project_id', $project->id)
            ->where('email', strtolower($email))->orderByDesc('id')->firstOrFail();
        $queued = null;
        Queue::assertPushed(DeliverTaskForgeMail::class, function (DeliverTaskForgeMail $job) use ($latest, &$queued) {
            if ($job->kind === 'invitation' && $job->recordId === $latest->id) {
                $queued = $job;
                return true;
            }
            return false;
        });
        $this->assertNotNull($queued);
        $token = Crypt::decryptString($queued->encryptedToken);
        $queued->handle();
        Mail::assertSent(ProjectInvitationMail::class, fn (ProjectInvitationMail $mail) =>
            $mail->hasTo($email) && $mail->token === $token);
        $this->assertDatabaseHas('project_invitations', [
            'project_id' => $project->id,
            'email' => strtolower($email),
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    public function test_owner_invitation_is_emailed_and_acceptance_is_single_use(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'colleague@example.test']);
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $token = $this->invite($project, $owner, $invitee->email);

        $this->as($invitee)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertOk()->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.role', 'editor');
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id, 'user_id' => $invitee->id, 'role' => 'editor',
        ]);
        $this->as($invitee)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('project_user', 1);
    }

    public function test_unrelated_user_cannot_accept_another_users_invitation(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email' => 'recipient@example.test']);
        $attacker = User::factory()->create(['email' => 'attacker@example.test']);
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $token = $this->invite($project, $owner, $recipient->email);

        $this->as($attacker)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id, 'user_id' => $attacker->id,
        ]);
        $this->as($recipient)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertOk();
    }

    public function test_expired_and_revoked_invitations_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $token = $this->invite($project, $owner, $recipient->email);

        $record = ProjectInvitation::firstOrFail();
        $record->update(['expires_at' => now()->subSecond()]);
        $this->as($recipient)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        $record->update(['expires_at' => now()->addDay()]);
        $this->as($owner)->deleteJson("/api/v2/projects/{$project->id}/invitations/{$record->id}")
            ->assertOk()->assertJsonPath('message', 'Invitation revoked.');
        $this->as($recipient)->postJson('/api/v2/invitations/accept', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_only_owner_can_create_list_and_revoke_invitations(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->as($viewer)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $outsider->email, 'role' => 'editor',
        ])->assertForbidden();

        $token = $this->invite($project, $owner, $outsider->email);
        $record = ProjectInvitation::firstOrFail();

        $this->as($viewer)->getJson("/api/v2/projects/{$project->id}/invitations")->assertForbidden();
        $this->as($outsider)->deleteJson("/api/v2/projects/{$project->id}/invitations/{$record->id}")->assertForbidden();
        $this->as($owner)->getJson("/api/v2/projects/{$project->id}/invitations?per_page=1")
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['token_hash' => hash('sha256', $token)]);
    }

    public function test_reinvitation_invalidates_previous_token(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $old = $this->invite($project, $owner, $invitee->email);
        $new = $this->invite($project, $owner, $invitee->email, 'viewer');
        $this->assertNotSame($old, $new);

        $this->as($invitee)->postJson('/api/v2/invitations/accept', ['token' => $old])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->as($invitee)->postJson('/api/v2/invitations/accept', ['token' => $new])
            ->assertOk()->assertJsonPath('data.role', 'viewer');
    }

    public function test_role_validation_prevents_inviting_superadmin_or_owner(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => 'example@example.test', 'role' => 'superadmin',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->as($owner)->postJson("/api/v2/projects/{$project->id}/invitations", [
            'email' => $owner->email, 'role' => 'editor',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_non_member_cannot_list_other_projects_invitations(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->as($attacker)->getJson("/api/v2/projects/{$project->id}/invitations")
            ->assertForbidden();
    }
}
