<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\ActivityLog;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        $this->logActivity('created', $task, null, $task->toArray());
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        $this->logActivity('updated', $task, $task->getOriginal(), $task->getChanges());
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        $this->logActivity('deleted', $task, $task->toArray(), null);
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
