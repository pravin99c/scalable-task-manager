<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\ActivityLog;
use App\Services\ProjectActivityRecorder;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        $this->logActivity('created', $task, null, $task->toArray());
        app(ProjectActivityRecorder::class)->record('created', $task);
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        $this->logActivity('updated', $task, $task->getOriginal(), $task->getChanges());
        app(ProjectActivityRecorder::class)->record('updated', $task);
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        $this->logActivity('deleted', $task, $task->toArray(), null);
        app(ProjectActivityRecorder::class)->record('deleted', $task);
    }

    protected function logActivity(string $action, Task $task, ?array $oldData, ?array $newData): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($task),
            'model_id' => $task->id,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
