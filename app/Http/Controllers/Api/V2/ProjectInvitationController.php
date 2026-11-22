<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\DeliverTaskForgeMail;
use Illuminate\Support\Facades\Crypt;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        abort_if($request->user()->email_verified_at === null, 403, 'Verify your email before sending invitations.');

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
            // Serialize the quota across ALL project owners. Lock the same
            // SHA-256 recipient key for every sender; lock issuer and project
            // keys too, in a deterministic order to reduce deadlock cycles.
            $keys = [
                hash('sha256', 'recipient:'.$email),
                hash('sha256', 'project:'.$project->getKey()),
                hash('sha256', 'issuer:'.$request->user()->getKey()),
            ];
            sort($keys, SORT_STRING);

            foreach ($keys as $key) {
                DB::table('invitation_rate_limit_locks')->insertOrIgnore([
                    'key_hash' => $key,
                    'last_seen_at' => $now,
                ]);
            }

            foreach ($keys as $key) {
                DB::table('invitation_rate_limit_locks')
                    ->where('key_hash', $key)->lockForUpdate()->first();
                DB::table('invitation_rate_limit_locks')
                    ->where('key_hash', $key)->update(['last_seen_at' => $now]);
            }

            $since = $now->copy()->subDay();
            $recipientCount = ProjectInvitation::query()->where('email', $email)
                ->where('created_at', '>=', $since)->count();
            $projectCount = ProjectInvitation::query()->where('project_id', $project->id)
                ->where('created_at', '>=', $since)->count();
            $issuerCount = ProjectInvitation::query()->where('invited_by', $request->user()->getKey())
                ->where('created_at', '>=', $since)->count();
            if ($recipientCount >= 3 || $projectCount >= 20 || $issuerCount >= 50) {
                abort(429, 'Invitation sending limit reached.');
            }

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

            // Force the database queue, which shares this transaction's DB
            // connection. Enqueue and invitation commit atomically; a worker
            // cannot see this job until commit. Queue payload is encrypted.
            DeliverTaskForgeMail::dispatch('invitation', $invitation->id, Crypt::encryptString($token))
                ->onConnection('database')->onQueue('mail')->beforeCommit();

            return $invitation;
        }, 3);

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

        // Same-email account matching alone was insecure: the recipient must
        // prove control of the mailbox through a separate verification token.
        abort_if($request->user()->email_verified_at === null, 403, 'Verify your email before accepting an invitation.');

        $result = DB::transaction(function () use ($request, $data) {
            // Serialize acceptance against account-email changes. Recheck the
            // persisted verified identity while holding the same user lock
            // used by EmailChangeService.
            $invitee = \App\Models\User::query()->lockForUpdate()
                ->findOrFail($request->user()->getKey());
            if ($invitee->email_verified_at === null) {
                abort(403, 'Verify your email before accepting an invitation.');
            }
            $invitation = ProjectInvitation::query()
                ->where('token_hash', hash('sha256', $data['token']))
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isPending()
                || Str::lower($invitee->email) !== $invitation->email) {
                throw ValidationException::withMessages(['token' => ['Invitation is invalid, expired, or not assigned to this account.']]);
            }

            $project = Project::find($invitation->project_id);
            if (! $project || Str::lower($project->creator->email) === $invitation->email) {
                throw ValidationException::withMessages(['token' => ['Invitation cannot be accepted.']]);
            }

            // If the invitee is already a member, do not use a token to
            // change a role set separately by the owner.
            if ($project->hasMember($invitee)) {
                throw ValidationException::withMessages(['token' => ['You are already a member of this project.']]);
            }

            $project->members()->attach($invitee->getKey(), [
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
