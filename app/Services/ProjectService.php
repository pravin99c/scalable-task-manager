<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Interfaces\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProjectService extends BaseService
{
    public function __construct(private ProjectRepositoryInterface $projectRepository)
    {
    }

    public function getAllProjects(User $user): Collection
    {
        // Never use a globally shared "projects.all" cache for private data.
        return Project::query()
            ->where('created_by', $user->getKey())
            ->orderBy('id')
            ->get();
    }

    public function createProject(array $data)
    {
        return $this->projectRepository->create($data);
    }

    public function updateProject(array $data, int $id)
    {
        return $this->projectRepository->update($data, $id);
    }

    public function deleteProject(int $id)
    {
        return $this->projectRepository->delete($id);
    }

    public function getProjectById(int $id)
    {
        return $this->projectRepository->find($id);
    }
}
