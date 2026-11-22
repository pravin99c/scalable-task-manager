<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'project_id' => Project::factory(),
            'assigned_to' => null,
            'status' => $this->faker->randomElement(['TODO', 'IN_PROGRESS', 'DONE']),
            'priority' => $this->faker->randomElement(['LOW', 'MEDIUM', 'HIGH']),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'created_by' => User::factory(),
        ];
    }
}
