<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class ProcessTaskAssignment implements ShouldQueue
{
    use Queueable;

    public $task;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->task->assigned_to) {
            Log::info("Simulating: Sending assignment email to user {$this->task->assigned_to} for task {$this->task->id}");
        }
    }
}
