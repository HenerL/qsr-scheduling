<?php

namespace App\Repositories;

use App\Models\ManagerPosition;
use App\Repositories\Interfaces\ManagerPositionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ManagerPositionRepository implements ManagerPositionRepositoryInterface
{
    private const SORTABLE = ['position_name', 'sort_order', 'created_at'];

    public function getPaginated(int $storeId, array $params): LengthAwarePaginator
    {
        $query = ManagerPosition::forStore($storeId);

        if (!empty($params['search'])) {
            $query->where('position_name', 'like', '%' . $params['search'] . '%');
        }

        if (isset($params['is_active']) && $params['is_active'] !== null && $params['is_active'] !== '') {
            $query->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = in_array($params['sort_by'] ?? null, self::SORTABLE, true)
            ? $params['sort_by']
            : 'sort_order';

        return $query
            ->orderBy($sortBy, $params['sort_dir'] ?? 'asc')
            ->orderBy('position_id')
            ->paginate(
                perPage: $params['per_page'] ?? 15,
                page: $params['page'] ?? 1,
            );
    }

    public function findInStore(int $storeId, int $positionId): ?ManagerPosition
    {
        return ManagerPosition::forStore($storeId)->find($positionId);
    }

    public function nameTaken(int $storeId, string $name, ?int $exceptId = null): bool
    {
        return ManagerPosition::forStore($storeId)
            ->where('position_name', $name)
            ->when($exceptId !== null, fn ($q) => $q->where('position_id', '!=', $exceptId))
            ->exists();
    }

    public function create(int $storeId, array $data): ManagerPosition
    {
        return ManagerPosition::create([...$data, 'store_id' => $storeId]);
    }

    public function update(ManagerPosition $position, array $data): void
    {
        $position->fill($data)->save();
    }

    public function deactivate(ManagerPosition $position): void
    {
        $position->fill(['is_active' => false])->save();
    }

    public function seedDefaults(int $storeId, array $defaults): int
    {
        return DB::transaction(function () use ($storeId, $defaults): int {
            $created = 0;

            foreach ($defaults as $index => $default) {
                $exists = ManagerPosition::forStore($storeId)
                    ->where('position_name', $default['position_name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                ManagerPosition::create([
                    'store_id' => $storeId,
                    'position_name' => $default['position_name'],
                    'position_description' => $default['position_description'] ?? null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
                $created++;
            }

            return $created;
        });
    }
}
