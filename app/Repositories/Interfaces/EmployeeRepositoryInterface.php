<?php

namespace App\Repositories\Interfaces;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeRepositoryInterface
{
    public function getPaginated(int $storeId, array $params): LengthAwarePaginator;

    /** Board rows: active employees only, ordered the way the grid groups them. */
    public function getActiveForStore(int $storeId): Collection;

    public function findInStore(int $storeId, int $employeeId): ?Employee;

    public function findByUserId(int $userId): ?Employee;

    public function create(int $storeId, array $data): Employee;

    public function update(Employee $employee, array $data): void;

    public function deactivate(Employee $employee): void;

    public function syncStations(Employee $employee, array $stations): void;
}
