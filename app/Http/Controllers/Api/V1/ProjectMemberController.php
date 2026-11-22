<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\ProjectInvitation;
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
        abort_if($request->user()->email_verified_at === null, 403, 'Verify your email before managing members.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([$project->created_by])],
            'role' => ['required', Rule::in(['editor', 'viewer'])],
        ]);

        $target = User::findOrFail($data['user_id']);
        abort_if($target->email_verified_at === null, 422, 'The new member must verify their email first.');

        DB::transaction(function () use ($project, $data, $target) {
            ProjectInvitation::query()->where('project_id', $project->id)
                ->where('email', strtolower($target->email))
                ->whereNull('accepted_at')->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

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
            $removed = User::find($user);
            if ($removed) {
                ProjectInvitation::query()->where('project_id', $project->id)
                    ->where('email', strtolower($removed->email))
                    ->whereNull('accepted_at')->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
            $project->members()->detach($user);
            Task::query()->where('project_id', $project->id)
                ->where('assigned_to', $user)->update(['assigned_to' => null]);
        });

        return response()->json(['message' => 'Project member removed']);
    }
}
