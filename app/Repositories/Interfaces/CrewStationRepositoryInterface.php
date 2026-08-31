<?php

namespace App\Repositories\Interfaces;

use App\Models\CrewStation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CrewStationRepositoryInterface
{
    public function getPaginated(int $storeId, array $params): LengthAwarePaginator;

    /** Board grouping + the coverage warning's `min_crew_required` source. */
    public function getActiveForStore(int $storeId): Collection;

    public function findInStore(int $storeId, int $stationId): ?CrewStation;

    public function existingIdsInStore(int $storeId, array $stationIds): array;

    public function nameTaken(int $storeId, string $name, ?int $exceptId = null): bool;

    public function create(int $storeId, array $data): CrewStation;

    public function update(CrewStation $station, array $data): void;

    public function deactivate(CrewStation $station): void;

    public function seedDefaults(int $storeId, array $defaults): int;
}
