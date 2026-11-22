<?php

namespace App\Services;

use App\Events\ProjectActivityChanged;
use App\Models\ProjectActivity;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Runs from TaskObserver during the task write. Activity and notification rows
 * share the write transaction; private broadcasts dispatch after its commit.
 * No emails are created or sent from this service.
 */
class ProjectActivityRecorder
{
    public function record(string $action, Task $task): void
    {
        $projectId = (int) $task->project_id;
        $actorId = Auth::id();
        $title = mb_substr($task->title, 0, 255);

        DB::transaction(function () use ($projectId, $actorId, $title, $task, $action) {
            $activity = ProjectActivity::create([
                'project_id' => $projectId,
                'actor_id' => $actorId,
                'task_id' => $task->getKey(),
                'action' => $action,
                'title' => $title,
            ]);

            $owner = DB::table('projects')
                ->where('id', $projectId)->value('created_by');
            if ($owner === null) {
                return;
            }

            $recipients = DB::table('project_user')
                ->where('project_id', $projectId)->pluck('user_id')->all();
            $recipients[] = (int) $owner;

            $now = now();
            $rows = [];
            foreach (array_unique(array_map('intval', $recipients)) as $recipient) {
                if ($actorId !== null && $recipient === (int) $actorId) {
                    continue;
                }

                $rows[] = [
                    'project_id' => $projectId,
                    'project_activity_id' => $activity->id,
                    'user_id' => $recipient,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('project_notifications')->insert($chunk);
            }

            // Publish only IDs; all details remain behind Gate::view checks.
            ProjectActivityChanged::dispatch($projectId, (int) $activity->id);
        });
    }
}
