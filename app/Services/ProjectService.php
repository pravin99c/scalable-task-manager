<?php

namespace App\Services;

use App\Repositories\Interfaces\ProjectRepositoryInterface;

class ProjectService extends BaseService
{
    protected $projectRepository;

    public function __construct(ProjectRepositoryInterface $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function getAllProjects()
    {
        return \Illuminate\Support\Facades\Cache::remember('projects.all', 3600, function () {
            return $this->projectRepository->all();
        });
    }

    public function createProject(array $data)
    {
        \Illuminate\Support\Facades\Cache::forget('projects.all');
        return $this->projectRepository->create($data);
    }

    public function updateProject(array $data, int $id)
    {
        \Illuminate\Support\Facades\Cache::forget('projects.all');
        return $this->projectRepository->update($data, $id);
    }

    public function deleteProject(int $id)
    {
        \Illuminate\Support\Facades\Cache::forget('projects.all');
        return $this->projectRepository->delete($id);
    }

    public function getProjectById(int $id)
    {
        return $this->projectRepository->find($id);
    }
}
