<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ProjectNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectNotificationController extends Controller
{
    private function accessible(Request $request)
    {
        $user = $request->user();

        return ProjectNotification::query()
            ->where('user_id', $user->getKey())
            ->whereHas('project', fn ($projects) => $projects->accessibleTo($user));
    }

    public function index(Request $request): JsonResponse
    {
        $params = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $unread = (clone $this->accessible($request))->whereNull('read_at')->count();

        $page = $this->accessible($request)
            ->with(['activity.actor:id,name'])
            ->orderByDesc('id')
            ->paginate($params['per_page'] ?? 20);

        $response = ApiResponse::page($page, fn (ProjectNotification $notification) => [
            'id' => $notification->id,
            'project_id' => $notification->project_id,
            'read_at' => $notification->read_at,
            'activity' => $notification->activity ? [
                'id' => $notification->activity->id,
                'action' => $notification->activity->action,
                'task_id' => $notification->activity->task_id,
                'title' => $notification->activity->title,
                'actor' => $notification->activity->actor ? [
                    'id' => $notification->activity->actor->id,
                    'name' => $notification->activity->actor->name,
                ] : null,
                'created_at' => $notification->activity->created_at,
            ] : null,
        ]);

        $body = $response->getData(true);
        $body['unread_count'] = $unread;

        return response()->json($body);
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $row = $this->accessible($request)->whereKey($notification)->firstOrFail();
        if ($row->read_at === null) {
            $row->update(['read_at' => now()]);
        }

        return ApiResponse::message('Notification marked read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        // Access-filtered subquery also hides removed project memberships.
        $this->accessible($request)->whereNull('read_at')->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::message('Notifications marked read.');
    }
}
