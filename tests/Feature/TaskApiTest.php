<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\TaskUpdated;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $token;
    private $project;

    /**
     * Set up an authenticated user and a project before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
        $this->token = $this->user->createToken('test_token')->plainTextToken;
        $this->project = Project::factory()->create(['created_by' => $this->user->id]);
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_a_task(): void
    {
        Event::fake([TaskUpdated::class]);

        $payload = [
            'title' => 'Test Task',
            'description' => 'Task description',
            'project_id' => $this->project->id,
            'status' => 'TODO',
            'priority' => 'HIGH',
        ];

        $response = $this->postJson('/api/v1/tasks', $payload, $this->authHeader());

        $response->assertStatus(201)
                 ->assertJsonFragment(['title' => 'Test Task'])
                 ->assertJsonFragment(['status' => 'TODO']);

        $this->assertDatabaseHas('tasks', ['title' => 'Test Task']);
        Event::assertDispatched(TaskUpdated::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_a_title_to_create_a_task(): void
    {
        $response = $this->postJson('/api/v1/tasks', [
            'project_id' => $this->project->id,
        ], $this->authHeader());

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_a_valid_project_id_to_create_a_task(): void
    {
        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Test Task',
            'project_id' => 999,
        ], $this->authHeader());

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['project_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_list_all_tasks(): void
    {
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/tasks', $this->authHeader());

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_show_a_single_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/tasks/' . $task->id, $this->authHeader());

        $response->assertStatus(200)
                 ->assertJsonFragment(['title' => $task->title]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_task_status(): void
    {
        Event::fake([TaskUpdated::class]);

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'TODO',
        ]);

        $response = $this->putJson(
            '/api/v1/tasks/' . $task->id,
            ['status' => 'IN_PROGRESS'],
            $this->authHeader()
        );

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'IN_PROGRESS']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'IN_PROGRESS']);
        Event::assertDispatched(TaskUpdated::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_delete_a_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson('/api/v1/tasks/' . $task->id, [], $this->authHeader());

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Task deleted successfully']);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_status_enum_on_update(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson(
            '/api/v1/tasks/' . $task->id,
            ['status' => 'INVALID_STATUS'],
            $this->authHeader()
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_authentication_to_access_tasks(): void
    {
        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(401);
    }
}
