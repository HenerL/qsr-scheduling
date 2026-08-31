<?php

namespace App\Repositories;

use App\Models\CrewStation;
use App\Repositories\Interfaces\CrewStationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CrewStationRepository implements CrewStationRepositoryInterface
{
    private const SORTABLE = ['station_name', 'sort_order', 'created_at'];

    public function getPaginated(int $storeId, array $params): LengthAwarePaginator
    {
        $query = CrewStation::forStore($storeId);

        if (!empty($params['search'])) {
            $query->where('station_name', 'like', '%' . $params['search'] . '%');
        }

        if (isset($params['is_active']) && $params['is_active'] !== null && $params['is_active'] !== '') {
            $query->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = in_array($params['sort_by'] ?? null, self::SORTABLE, true)
            ? $params['sort_by']
            : 'sort_order';

        return $query
            ->orderBy($sortBy, $params['sort_dir'] ?? 'asc')
            ->orderBy('station_id')
            ->paginate(
                perPage: $params['per_page'] ?? 15,
                page: $params['page'] ?? 1,
            );
    }

    public function getActiveForStore(int $storeId): Collection
    {
        return CrewStation::forStore($storeId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('station_id')
            ->get();
    }

    public function findInStore(int $storeId, int $stationId): ?CrewStation
    {
        return CrewStation::forStore($storeId)->find($stationId);
    }

    public function existingIdsInStore(int $storeId, array $stationIds): array
    {
        if ($stationIds === []) {
            return [];
        }

        return CrewStation::forStore($storeId)
            ->whereIn('station_id', $stationIds)
            ->pluck('station_id')
            ->all();
    }

    public function nameTaken(int $storeId, string $name, ?int $exceptId = null): bool
    {
        return CrewStation::forStore($storeId)
            ->where('station_name', $name)
            ->when($exceptId !== null, fn ($q) => $q->where('station_id', '!=', $exceptId))
            ->exists();
    }

    public function create(int $storeId, array $data): CrewStation
    {
        return CrewStation::create([...$data, 'store_id' => $storeId]);
    }

    public function update(CrewStation $station, array $data): void
    {
        $station->fill($data)->save();
    }

    public function deactivate(CrewStation $station): void
    {
        $station->fill(['is_active' => false])->save();
    }

    public function seedDefaults(int $storeId, array $defaults): int
    {
        return DB::transaction(function () use ($storeId, $defaults): int {
            $created = 0;

            foreach ($defaults as $index => $default) {
                $exists = CrewStation::forStore($storeId)
                    ->where('station_name', $default['station_name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                CrewStation::create([
                    'store_id' => $storeId,
                    'station_name' => $default['station_name'],
                    'station_description' => $default['station_description'] ?? null,
                    'min_crew_required' => $default['min_crew_required'] ?? null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
                $created++;
            }

            return $created;
        });
    }
}
