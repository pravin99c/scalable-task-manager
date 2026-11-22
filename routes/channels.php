<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->getKey() === (int) $id;
});

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    // No project-membership system exists yet. Authorize only the owner.
    return Project::query()
        ->whereKey($projectId)
        ->where('created_by', $user->getKey())
        ->exists();
});
