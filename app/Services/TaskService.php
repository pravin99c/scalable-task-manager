<?php

namespace App\Services;

use App\Repositories\Interfaces\TaskRepositoryInterface;

class TaskService extends BaseService
{
    protected $taskRepository;

    public function __construct(TaskRepositoryInterface $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function getAllTasks()
    {
        return \Illuminate\Support\Facades\Cache::remember('tasks.all', 3600, function () {
            return $this->taskRepository->all();
        });
    }

    public function createTask(array $data)
    {
        \Illuminate\Support\Facades\Cache::forget('tasks.all');
        $task = $this->taskRepository->create($data);
        
        if (!empty($data['assigned_to'])) {
            \App\Jobs\ProcessTaskAssignment::dispatch($task);
        }
        
        event(new \App\Events\TaskUpdated($task));
        
        return $task;
    }

    public function updateTask(array $data, int $id)
    {
        \Illuminate\Support\Facades\Cache::forget('tasks.all');
        $this->taskRepository->update($data, $id);
        
        // Re-fetch task since update returns boolean
        $updatedTask = $this->taskRepository->find($id);

        if (!empty($data['assigned_to'])) {
            \App\Jobs\ProcessTaskAssignment::dispatch($updatedTask);
        }
        
        event(new \App\Events\TaskUpdated($updatedTask));
        
        return $updatedTask;
    }

    public function deleteTask(int $id)
    {
        \Illuminate\Support\Facades\Cache::forget('tasks.all');
        return $this->taskRepository->delete($id);
    }

    public function getTaskById(int $id)
    {
        return $this->taskRepository->find($id);
    }
}
