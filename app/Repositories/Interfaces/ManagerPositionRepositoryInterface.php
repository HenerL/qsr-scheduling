<?php

namespace App\Repositories\Interfaces;

use App\Models\ManagerPosition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ManagerPositionRepositoryInterface
{
    public function getPaginated(int $storeId, array $params): LengthAwarePaginator;

    public function findInStore(int $storeId, int $positionId): ?ManagerPosition;

    public function nameTaken(int $storeId, string $name, ?int $exceptId = null): bool;

    public function create(int $storeId, array $data): ManagerPosition;

    public function update(ManagerPosition $position, array $data): void;

    public function deactivate(ManagerPosition $position): void;

    public function seedDefaults(int $storeId, array $defaults): int;
}
