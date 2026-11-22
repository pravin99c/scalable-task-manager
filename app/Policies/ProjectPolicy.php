<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->isOwner($user) || $project->hasMember($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->isOwner($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->isOwner($user);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $project->isOwner($user);
    }

    public function editTasks(User $user, Project $project): bool
    {
        return $project->isOwner($user) || $project->hasMember($user, 'editor');
    }
}
