<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\V2\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $params = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['TODO', 'IN_PROGRESS', 'DONE'])],
            'priority' => ['sometimes', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
        ]);

        $query = Task::query()->whereHas('project', fn ($projects) =>
            $projects->accessibleTo($request->user()));

        foreach (['project_id', 'status', 'priority'] as $key) {
            if (isset($params[$key])) {
                $query->where($key, $params[$key]);
            }
        }

        return ApiResponse::page(
            $query->orderBy('id')->paginate($params['per_page'] ?? 15),
            fn (Task $task) => (new TaskResource($task))->resolve($request),
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $data = $request->validated();
        Gate::authorize('editTasks', Project::findOrFail($data['project_id']));
        $data['created_by'] = $request->user()->getKey();

        return ApiResponse::item(
            (new TaskResource($this->tasks->createTask($data)))->resolve($request),
            201,
        );
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return ApiResponse::item((new TaskResource($task))->resolve($request));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);
        $this->tasks->updateTask($request->validated(), $task->id);

        return ApiResponse::item((new TaskResource($task->fresh()))->resolve($request));
    }

    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);
        $this->tasks->deleteTask($task->id);

        return ApiResponse::message('Task deleted successfully');
    }
}
