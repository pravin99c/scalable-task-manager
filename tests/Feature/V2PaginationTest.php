<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class V2PaginationTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): static
    {
        Auth::forgetGuards();
        $this->actingAs($user);
        return $this;
    }

    public function test_project_pagination_is_bounded_and_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = Project::factory()->count(4)->create(['created_by' => $owner->id]);
        Project::factory()->count(4)->create(['created_by' => $other->id]);

        $this->as($owner)->getJson('/api/v2/projects?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'per_page', 'last_page'], 'links' => ['first', 'last', 'prev', 'next']]);

        $this->as($other)->getJson('/api/v2/projects')->assertOk()->assertJsonPath('meta.total', 4);

        $this->as($owner)->getJson('/api/v2/projects?per_page=500')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['data', 'errors', 'meta', 'links']);
    }

    public function test_membership_projects_appear_but_unrelated_projects_do_not(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $shared = Project::factory()->create(['created_by' => $owner->id]);
        Project::factory()->create(['created_by' => $outsider->id]);
        $shared->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->as($viewer)->getJson('/api/v2/projects')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $shared->id);
        $this->as($outsider)->getJson("/api/v2/projects/{$shared->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_task_filters_do_not_expose_other_projects(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $owner->id]);
        $foreign = Project::factory()->create(['created_by' => $other->id]);
        Task::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id, 'status' => 'TODO']);
        Task::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id, 'status' => 'DONE']);
        Task::factory()->create(['project_id' => $foreign->id, 'created_by' => $other->id, 'status' => 'TODO']);

        $this->as($owner)->getJson("/api/v2/tasks?status=TODO&per_page=1")->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
        $this->as($owner)->getJson("/api/v2/tasks?project_id={$foreign->id}")->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->as($owner)->getJson('/api/v2/tasks?status=WRONG')->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_v1_project_list_remains_legacy_array_response(): void
    {
        $owner = User::factory()->create();
        Project::factory()->count(2)->create(['created_by' => $owner->id]);

        $this->as($owner)->getJson('/api/v1/projects')
            ->assertOk()->assertJsonCount(2);

        $this->as($owner)->getJson('/api/v2/projects?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_v2_resource_create_and_delete_use_standard_envelope(): void
    {
        $owner = User::factory()->create();
        $created = $this->as($owner)->postJson('/api/v2/projects', [
            'name' => 'Phase Two Project',
            'created_by' => 99999,
        ])->assertCreated()->assertJsonPath('data.name', 'Phase Two Project')
            ->assertJsonPath('meta', null)->assertJsonPath('links', null);
        $id = $created->json('data.id');
        $this->assertDatabaseHas('projects', ['id' => $id, 'created_by' => $owner->id]);
        $this->as($owner)->deleteJson("/api/v2/projects/{$id}")->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Project deleted successfully');
    }

    public function test_v2_unknown_resource_is_consistent_not_found(): void
    {
        $this->as(User::factory()->create())->getJson('/api/v2/projects/987654321')
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonStructure(['data', 'message', 'errors', 'meta', 'links']);
    }
}
