<?php

namespace Tests\Feature;

use App\Events\ProjectActivityChanged;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectNotification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProjectRealtimeCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();
        $this->actingAs($user);

        return $this;
    }

    public function test_task_lifecycle_creates_scoped_activity_and_notifies_other_members_without_mail(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($editor->id, ['role' => 'editor']);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $created = $this->as($owner)->postJson('/api/v2/tasks', [
            'project_id' => $project->id,
            'title' => 'Private task summary',
            'description' => 'Sensitive note never sent over broadcast',
            'status' => 'TODO',
        ])->assertCreated();
        $taskId = $created->json('data.id');

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'task_id' => $taskId,
            'action' => 'created',
        ]);
        $this->assertSame(2, ProjectNotification::query()->where('project_id', $project->id)->count());
        $this->assertDatabaseMissing('project_notifications', [
            'project_id' => $project->id, 'user_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('project_notifications', [
            'project_id' => $project->id, 'user_id' => $outsider->id,
        ]);

        $this->as($editor)->patchJson("/api/v2/tasks/{$taskId}", [
            'status' => 'IN_PROGRESS',
        ])->assertOk();
        $this->as($owner)->deleteJson("/api/v2/tasks/{$taskId}")->assertOk();

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            ProjectActivity::query()->where('project_id', $project->id)->orderBy('id')->pluck('action')->all()
        );

        $this->as($viewer)->getJson("/api/v2/projects/{$project->id}/activity")
            ->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.action', 'deleted');

        $this->as($outsider)->getJson("/api/v2/projects/{$project->id}/activity")->assertForbidden();
        $this->getJson('/api/v2/notifications')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_notification_read_state_is_user_scoped_and_revoked_members_cannot_read_old_notifications(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $project->members()->attach($member->id, ['role' => 'viewer']);
        $project->members()->attach($otherMember->id, ['role' => 'viewer']);

        $this->as($owner)->postJson('/api/v2/tasks', [
            'project_id' => $project->id,
            'title' => 'Team activity',
        ])->assertCreated();

        $memberNote = ProjectNotification::where('user_id', $member->id)->firstOrFail();
        $otherNote = ProjectNotification::where('user_id', $otherMember->id)->firstOrFail();

        $this->as($member)->getJson('/api/v2/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.id', $memberNote->id);

        $this->as($member)->patchJson("/api/v2/notifications/{$otherNote->id}/read")->assertNotFound();
        $this->as($member)->patchJson("/api/v2/notifications/{$memberNote->id}/read")->assertOk();
        $this->assertNotNull($memberNote->fresh()->read_at);
        $this->assertNull($otherNote->fresh()->read_at);

        $this->as($otherMember)->patchJson('/api/v2/notifications/read-all')->assertOk();
        $this->assertNotNull($otherNote->fresh()->read_at);

        $project->members()->detach($member->id);
        $this->as($member)->getJson('/api/v2/notifications')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->as($member)->patchJson("/api/v2/notifications/{$memberNote->id}/read")
            ->assertNotFound();
        $this->as($member)->getJson("/api/v2/projects/{$project->id}/activity")
            ->assertForbidden();
    }

    public function test_notification_lists_are_paginated_and_do_not_leak_other_project_data(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $otherProject = Project::factory()->create(['created_by' => User::factory()->create()->id]);
        $project->members()->attach($member->id, ['role' => 'viewer']);

        $this->as($owner)->postJson('/api/v2/tasks', [
            'project_id' => $project->id,
            'title' => 'Visible',
        ])->assertCreated();

        $this->as($otherProject->creator)->postJson('/api/v2/tasks', [
            'project_id' => $otherProject->id,
            'title' => 'Invisible',
        ])->assertCreated();

        $this->as($member)->getJson('/api/v2/notifications?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonMissing(['title' => 'Invisible']);

        $this->as($member)->getJson('/api/v2/notifications?per_page=999')->assertUnprocessable();
        $this->as($member)->getJson("/api/v2/projects/{$project->id}/activity?per_page=999")
            ->assertUnprocessable();
    }

    public function test_broadcast_signal_contains_only_ids_and_private_project_channel(): void
    {
        $signal = new ProjectActivityChanged(123, 456);
        $this->assertSame(['project_id' => 123, 'activity_id' => 456], $signal->broadcastWith());
        $this->assertSame('project.activity.changed', $signal->broadcastAs());
        $this->assertSame('private-project.123', $signal->broadcastOn()[0]->name);
        $this->assertNotContains('description', array_keys($signal->broadcastWith()));
    }
}
