<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(private TaskService $taskService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        return response()->json($this->taskService->getAllTasks($request->user()));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);
        $data = $request->validated();

        // FormRequest constrains project_id, but enforce the same rule at
        // the controller boundary to guard against later validation changes.
        Gate::authorize('editTasks', Project::findOrFail($data['project_id']));
        $data['created_by'] = $request->user()->getKey();

        return response()->json($this->taskService->createTask($data), 201);
    }

    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json($task->load(['project', 'assignee', 'creator']));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);
        $this->taskService->updateTask($request->validated(), $task->id);

        return response()->json($task->fresh());
    }

    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);
        $this->taskService->deleteTask($task->id);

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
