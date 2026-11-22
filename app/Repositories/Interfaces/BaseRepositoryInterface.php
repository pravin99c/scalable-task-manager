<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    /**
     * Get all instances of model
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Create a new record in the database
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * Update record in the database
     *
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function update(array $data, int $id): bool;

    /**
     * Remove record from the database
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find record by ID
     *
     * @param int $id
     * @return Model|null
     */
    public function find(int $id): ?Model;
}
