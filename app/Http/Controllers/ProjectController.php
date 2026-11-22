<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        // Preserve the existing JSON array contract while removing global caches.
        return response()->json($this->projectService->getAllProjects($request->user()));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);
        $data = $request->validated();
        $data['created_by'] = $request->user()->getKey();

        return response()->json($this->projectService->createProject($data), 201);
    }

    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json($project->load('tasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);
        $this->projectService->updateProject($request->validated(), $project->id);

        return response()->json($project->fresh());
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);
        $this->projectService->deleteProject($project->id);

        return response()->json(['message' => 'Project deleted successfully']);
    }
}
