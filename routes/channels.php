<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->getKey() === (int) $id;
});

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    $project = Project::find($projectId);
    return $project !== null && $user->can('view', $project);
});
