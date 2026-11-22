<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('isolation-test')->plainTextToken];
    }

    public function test_projects_and_tasks_are_not_visible_to_other_users(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id, 'name' => 'Owner Private Project']);
        $task = Task::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id, 'title' => 'Owner Private Task']);
        $headers = $this->tokenFor($stranger);

        $this->getJson('/api/v1/projects', $headers)->assertOk()->assertJsonMissing(['name' => $project->name]);
        $this->getJson('/api/v1/tasks', $headers)->assertOk()->assertJsonMissing(['title' => $task->title]);

        $this->getJson("/api/v1/projects/{$project->id}", $headers)->assertForbidden();
        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hijacked'], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/projects/{$project->id}", [], $headers)->assertForbidden();

        $this->getJson("/api/v1/tasks/{$task->id}", $headers)->assertForbidden();
        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'DONE'], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$task->id}", [], $headers)->assertForbidden();
        $this->getJson("/api/v1/tasks/{$task->id}/comments", $headers)->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'unauthorized'], $headers)->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Owner Private Project']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => $task->status]);
    }

    public function test_user_cannot_create_a_task_inside_another_users_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->postJson('/api/v1/tasks', [
            'title' => 'unauthorized', 'project_id' => $project->id,
        ], $this->tokenFor($stranger))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }

    public function test_user_cannot_assign_a_task_to_an_unrelated_account(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Unauthorized assignment',
            'project_id' => $project->id,
            'assigned_to' => $stranger->id,
        ], $this->tokenFor($owner))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_a_user_cannot_create_a_privileged_admin_session(): void
    {
        $member = User::factory()->create(['role' => 'admin']);
        $this->actingAs($member)->get('/admin/dashboard')->assertForbidden();
    }
}
