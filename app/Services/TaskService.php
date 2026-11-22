<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Interfaces\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaskService extends BaseService
{
    public function __construct(private TaskRepositoryInterface $taskRepository)
    {
    }

    public function getAllTasks(User $user): Collection
    {
        return Task::query()
            ->whereHas('project', fn ($query) => $query->accessibleTo($user))
            ->orderBy('id')
            ->get();
    }

    public function createTask(array $data)
    {
        $task = $this->taskRepository->create($data);

        if (!empty($data['assigned_to'])) {
            \App\Jobs\ProcessTaskAssignment::dispatch($task);
        }

        event(new \App\Events\TaskUpdated($task));

        return $task;
    }

    public function updateTask(array $data, int $id)
    {
        $this->taskRepository->update($data, $id);
        $updatedTask = $this->taskRepository->find($id);

        if (!empty($data['assigned_to'])) {
            \App\Jobs\ProcessTaskAssignment::dispatch($updatedTask);
        }

        event(new \App\Events\TaskUpdated($updatedTask));

        return $updatedTask;
    }

    public function deleteTask(int $id)
    {
        return $this->taskRepository->delete($id);
    }

    public function getTaskById(int $id)
    {
        return $this->taskRepository->find($id);
    }
}
