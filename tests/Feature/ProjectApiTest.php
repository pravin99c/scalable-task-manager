<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $token;

    /**
     * Set up an authenticated user before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_a_project(): void
    {
        $payload = [
            'name' => 'Test Project',
            'description' => 'A sample project for testing',
        ];

        $response = $this->postJson('/api/v1/projects', $payload, $this->authHeader());

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Test Project']);

        $this->assertDatabaseHas('projects', ['name' => 'Test Project']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_a_name_to_create_a_project(): void
    {
        $response = $this->postJson('/api/v1/projects', [], $this->authHeader());

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_list_all_projects(): void
    {
        Project::factory()->count(3)->create(['created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/projects', $this->authHeader());

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_show_a_single_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/projects/' . $project->id, $this->authHeader());

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => $project->name]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_a_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);

        $response = $this->putJson(
            '/api/v1/projects/' . $project->id,
            ['name' => 'Updated Project Name'],
            $this->authHeader()
        );

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Updated Project Name']);

        $this->assertDatabaseHas('projects', ['name' => 'Updated Project Name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_delete_a_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);

        $response = $this->deleteJson('/api/v1/projects/' . $project->id, [], $this->authHeader());

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Project deleted successfully']);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_authentication_to_access_projects(): void
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    }
}
