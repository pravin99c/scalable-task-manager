<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Authorize users to listen on their project's private channel
Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    // Any authenticated user can listen to project channels
    // You can refine this to check project membership
    return $user !== null;
});
