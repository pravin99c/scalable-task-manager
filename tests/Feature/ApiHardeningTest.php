<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user, string $label = 'client'): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken($label)->plainTextToken];
    }

    public function test_registration_rejects_short_password_and_spoofed_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Example User',
            'email' => 'example@example.test',
            'password' => 'shortpass',
            'password_confirmation' => 'shortpass',
            'role' => 'superadmin',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Example User',
            'email' => 'example@example.test',
            'password' => 'long-unique-test-password',
            'password_confirmation' => 'long-unique-test-password',
            'role' => 'superadmin',
        ])->assertCreated()->assertJsonPath('data.user.role', 'user');

        $user = User::where('email', 'example@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('long-unique-test-password', $user->password));
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->hasRole('superadmin'));
        $this->assertNotEmpty($response->json('data.access_token'));
    }

    public function test_logout_revokes_only_presented_token_and_preserves_other_device(): void
    {
        $user = User::factory()->create();
        $desktop = $user->createToken('desktop')->plainTextToken;
        $mobile = $user->createToken('mobile')->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$desktop,
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        // A feature test reuses the application's guard; simulate a fresh
        // HTTP request authentication cycle before verifying the token.
        Auth::forgetGuards();
        $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer '.$desktop,
        ])->assertUnauthorized();
        Auth::forgetGuards();
        $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer '.$mobile,
        ])->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_login_is_throttled_for_repeated_invalid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'rate@example.test']);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertStatus(429);
    }

    public function test_project_roles_cannot_be_escalated_through_payload(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $viewer->id,
            'role' => 'superadmin',
        ], $this->bearer($owner))->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ], $this->bearer($owner))->assertOk();

        Auth::forgetGuards();
        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $outsider->id,
            'role' => 'editor',
        ], $this->bearer($viewer))->assertForbidden();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id, 'user_id' => $outsider->id,
        ]);
    }

    public function test_viewer_cannot_comment_on_project_tasks(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->postJson("/api/v1/tasks/{$task->id}/comments", [
            'body' => 'Viewer should not be able to post',
        ], $this->bearer($viewer))->assertForbidden();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_task_payload_cannot_reassign_project_or_creator_on_update(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $otherProject = Project::factory()->create(['created_by' => $other->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
        ]);

        $this->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Safe title',
            'project_id' => $otherProject->id,
            'created_by' => $other->id,
            'status' => 'DONE',
        ], $this->bearer($owner))->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Safe title',
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status' => 'DONE',
        ]);
    }
}
