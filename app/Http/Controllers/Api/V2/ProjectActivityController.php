<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectActivityController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $params = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $page = ProjectActivity::query()
            ->where('project_id', $project->getKey())
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->paginate($params['per_page'] ?? 20);

        return ApiResponse::page($page, fn (ProjectActivity $activity) => [
            'id' => $activity->id,
            'project_id' => $activity->project_id,
            'task_id' => $activity->task_id,
            'action' => $activity->action,
            'title' => $activity->title,
            'actor' => $activity->actor ? [
                'id' => $activity->actor->id,
                'name' => $activity->actor->name,
            ] : null,
            'created_at' => $activity->created_at,
        ]);
    }
}
