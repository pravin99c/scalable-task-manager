<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectInvitationController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $params = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $page = ProjectInvitation::query()
            ->where('project_id', $project->getKey())
            ->orderByDesc('id')
            ->paginate($params['per_page'] ?? 15);

        return ApiResponse::page($page, fn (ProjectInvitation $invite) => $this->present($invite));
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(['editor', 'viewer'])],
        ]);

        $email = Str::lower(trim($data['email']));
        if (Str::lower((string) $project->creator->email) === $email) {
            throw ValidationException::withMessages(['email' => ['The project owner cannot be invited.']]);
        }

        $existingUser = \App\Models\User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser && $project->hasMember($existingUser)) {
            throw ValidationException::withMessages(['email' => ['This user is already a project member.']]);
        }

        // A random one-time token is delivered by email; only SHA-256 is stored.
        $token = Str::random(80);
        $now = now();

        $invitation = DB::transaction(function () use ($project, $data, $email, $token, $now, $request) {
            // Expire/revoke any outstanding token for the same project/email.
            ProjectInvitation::query()
                ->where('project_id', $project->id)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            $invitation = ProjectInvitation::create([
                'project_id' => $project->id,
                'invited_by' => $request->user()->getKey(),
                'email' => $email,
                'role' => $data['role'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => $now->copy()->addDays(7),
            ]);

            // Any send failure rolls back the invitation record in this
            // synchronous implementation; production should adopt an outbox.
            Mail::to($email)->send(new ProjectInvitationMail(
                $project->name,
                $data['role'],
                $token,
                $invitation->expires_at->utc()->format('Y-m-d H:i:s'),
            ));

            return $invitation;
        });

        return ApiResponse::item($this->present($invitation), 201);
    }

    public function destroy(Project $project, ProjectInvitation $invitation): JsonResponse
    {
        Gate::authorize('manageMembers', $project);
        abort_unless((int) $invitation->project_id === (int) $project->getKey(), 404);
        abort_unless($invitation->isPending(), 404);

        $invitation->update(['revoked_at' => now()]);

        return ApiResponse::message('Invitation revoked.');
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:40', 'max:256'],
        ]);

        $result = DB::transaction(function () use ($request, $data) {
            $invitation = ProjectInvitation::query()
                ->where('token_hash', hash('sha256', $data['token']))
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isPending()
                || Str::lower($request->user()->email) !== $invitation->email) {
                throw ValidationException::withMessages(['token' => ['Invitation is invalid, expired, or not assigned to this account.']]);
            }

            $project = Project::find($invitation->project_id);
            if (! $project || Str::lower($project->creator->email) === $invitation->email) {
                throw ValidationException::withMessages(['token' => ['Invitation cannot be accepted.']]);
            }

            // If the invitee is already a member, do not use a token to
            // change a role set separately by the owner.
            if ($project->hasMember($request->user())) {
                throw ValidationException::withMessages(['token' => ['You are already a member of this project.']]);
            }

            $project->members()->attach($request->user()->getKey(), [
                'role' => $invitation->role,
            ]);
            $invitation->forceFill(['accepted_at' => now()])->save();

            // Other pending invitations cannot later override accepted access.
            ProjectInvitation::query()
                ->where('project_id', $project->getKey())
                ->where('email', $invitation->email)
                ->whereKeyNot($invitation->getKey())
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return [
                'project_id' => $project->getKey(),
                'role' => $invitation->role,
            ];
        });

        return ApiResponse::item($result);
    }

    private function present(ProjectInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'project_id' => $invitation->project_id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'accepted_at' => $invitation->accepted_at?->toIso8601String(),
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
        ];
    }
}
