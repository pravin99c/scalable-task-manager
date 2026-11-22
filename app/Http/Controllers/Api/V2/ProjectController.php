<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\V2\ProjectResource;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $params = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'search' => ['sometimes', 'string', 'max:120'],
        ]);

        $query = Project::query()->accessibleTo($request->user());

        if (! empty($params['search'])) {
            $query->where('name', 'like', '%'.addcslashes($params['search'], '%_\\').'%');
        }

        return ApiResponse::page(
            $query->orderBy('id')->paginate($params['per_page'] ?? 15),
            fn (Project $project) => (new ProjectResource($project))->resolve($request),
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->getKey();

        return ApiResponse::item(
            (new ProjectResource($this->projects->createProject($data)))->resolve($request),
            201,
        );
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return ApiResponse::item((new ProjectResource($project))->resolve($request));
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);
        $this->projects->updateProject($request->validated(), $project->id);

        return ApiResponse::item((new ProjectResource($project->fresh()))->resolve($request));
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);
        $this->projects->deleteProject($project->id);

        return ApiResponse::message('Project deleted successfully');
    }
}
