<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Retained for legacy in-process consumers and PHPUnit tests. The previous
 * ShouldBroadcast event serialized an entire Task record to WebSockets.
 * The public realtime contract is now ProjectActivityChanged (ID-only).
 */
class TaskUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Task $task) {}
}
