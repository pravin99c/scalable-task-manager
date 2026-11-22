<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Wake-up signal only: never broadcast full task records, private notes,
 * invitation tokens or membership lists. Receivers refetch authorized APIs.
 */
class ProjectActivityChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $projectId,
        public readonly int $activityId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->projectId)];
    }

    public function broadcastAs(): string
    {
        return 'project.activity.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'activity_id' => $this->activityId,
        ];
    }
}
