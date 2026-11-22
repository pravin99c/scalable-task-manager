<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('membership-test')->plainTextToken];
    }

    public function test_owner_can_add_editor_who_can_access_project_and_create_tasks(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $editor->id,
            'role' => 'editor',
        ], $this->auth($owner))->assertOk();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);

        $this->getJson("/api/v1/projects/{$project->id}", $this->auth($editor))->assertOk();
        $this->getJson('/api/v1/projects', $this->auth($editor))
            ->assertOk()->assertJsonFragment(['id' => $project->id]);

        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Shared task',
            'assigned_to' => $editor->id,
        ], $this->auth($editor))->assertCreated();

        $this->getJson('/api/v1/tasks', $this->auth($editor))
            ->assertOk()->assertJsonFragment(['title' => 'Shared task']);
    }

    public function test_viewer_can_read_but_cannot_modify_project_or_tasks(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->getJson("/api/v1/projects/{$project->id}", $this->auth($viewer))->assertOk();
        $this->getJson("/api/v1/tasks/{$task->id}", $this->auth($viewer))->assertOk();
        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'No'], $this->auth($viewer))->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$task->id}", [], $this->auth($viewer))->assertForbidden();
        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'DONE'], $this->auth($viewer))->assertForbidden();
        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'Not allowed',
        ], $this->auth($viewer))->assertUnprocessable()->assertJsonValidationErrors('project_id');
    }

    public function test_unrelated_user_cannot_read_members_or_manage_membership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);

        $this->getJson("/api/v1/projects/{$project->id}/members", $this->auth($other))->assertForbidden();
        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $other->id, 'role' => 'editor',
        ], $this->auth($other))->assertForbidden();
        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$other->id}", [], $this->auth($other))->assertForbidden();
    }

    public function test_owner_can_revoke_access_and_outstanding_task_assignment(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($editor->id, ['role' => 'editor']);
        $task = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'assigned_to' => $editor->id,
        ]);

        $this->deleteJson("/api/v1/projects/{$project->id}/members/{$editor->id}", [], $this->auth($owner))
            ->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => null]);
        $this->getJson("/api/v1/projects/{$project->id}", $this->auth($editor))->assertForbidden();
        $this->getJson("/api/v1/tasks/{$task->id}", $this->auth($editor))->assertForbidden();
    }

    public function test_editor_cannot_manage_members_or_delete_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($editor->id, ['role' => 'editor']);

        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $other->id, 'role' => 'editor',
        ], $this->auth($editor))->assertForbidden();

        $this->deleteJson("/api/v1/projects/{$project->id}", [], $this->auth($editor))->assertForbidden();
    }

    public function test_non_editor_cannot_be_assigned_task(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Restricted', 'project_id' => $project->id, 'assigned_to' => $viewer->id,
        ], $this->auth($owner))->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    }

    public function test_project_owner_cannot_be_added_as_regular_member(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $this->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $owner->id, 'role' => 'editor',
        ], $this->auth($owner))->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }
}
