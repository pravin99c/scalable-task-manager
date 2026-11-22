<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json([
            'owner' => $project->creator()->first(['id', 'name', 'email']),
            'members' => $project->members()
                ->select('users.id', 'users.name', 'users.email')
                ->orderBy('users.id')
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                ]),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([$project->created_by])],
            'role' => ['required', Rule::in(['editor', 'viewer'])],
        ]);

        DB::transaction(function () use ($project, $data) {
            $project->members()->syncWithoutDetaching([
                $data['user_id'] => ['role' => $data['role']],
            ]);

            // A downgraded viewer must not remain assigned tasks.
            if ($data['role'] === 'viewer') {
                Task::query()->where('project_id', $project->id)
                    ->where('assigned_to', $data['user_id'])
                    ->update(['assigned_to' => null]);
            }
        });

        return response()->json(['message' => 'Project member saved', 'user_id' => $data['user_id'], 'role' => $data['role']]);
    }

    public function destroy(Project $project, int $user): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        abort_if((int) $project->created_by === $user, 422, 'The project owner cannot be removed.');
        abort_unless($project->members()->whereKey($user)->exists(), 404);

        DB::transaction(function () use ($project, $user) {
            $project->members()->detach($user);
            Task::query()->where('project_id', $project->id)
                ->where('assigned_to', $user)->update(['assigned_to' => null]);
        });

        return response()->json(['message' => 'Project member removed']);
    }
}
