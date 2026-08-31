<?php

namespace App\Repositories;

use App\Models\ShiftTemplate;
use App\Repositories\Interfaces\ShiftTemplateRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ShiftTemplateRepository implements ShiftTemplateRepositoryInterface
{
    private const SORTABLE = ['template_name', 'start_time', 'sort_order', 'created_at'];

    public function getPaginated(int $storeId, array $params): LengthAwarePaginator
    {
        $query = ShiftTemplate::forStore($storeId);

        if (!empty($params['search'])) {
            $search = '%' . $params['search'] . '%';
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('template_name', 'like', $search)
                    ->orWhere('template_code', 'like', $search);
            });
        }

        if (isset($params['is_active']) && $params['is_active'] !== null && $params['is_active'] !== '') {
            $query->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($params['applies_to'])) {
            $query->whereIn('applies_to', [$params['applies_to'], 'both']);
        }

        $sortBy = in_array($params['sort_by'] ?? null, self::SORTABLE, true)
            ? $params['sort_by']
            : 'sort_order';

        return $query
            ->orderBy($sortBy, $params['sort_dir'] ?? 'asc')
            ->orderBy('start_time')
            ->orderBy('id')
            ->paginate(
                perPage: $params['per_page'] ?? 15,
                page: $params['page'] ?? 1,
            );
    }

    public function getActiveForStore(int $storeId): Collection
    {
        return ShiftTemplate::forStore($storeId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    public function findInStore(int $storeId, int $templateId): ?ShiftTemplate
    {
        return ShiftTemplate::forStore($storeId)->find($templateId);
    }

    public function nameTaken(int $storeId, string $name, ?int $exceptId = null): bool
    {
        return ShiftTemplate::forStore($storeId)
            ->where('template_name', $name)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    public function create(int $storeId, array $data): ShiftTemplate
    {
        return ShiftTemplate::create([...$data, 'store_id' => $storeId]);
    }

    public function update(ShiftTemplate $template, array $data): void
    {
        $template->fill($data)->save();
    }

    public function deactivate(ShiftTemplate $template): void
    {
        $template->fill(['is_active' => false])->save();
    }

    public function seedDefaults(int $storeId, array $defaults): int
    {
        return DB::transaction(function () use ($storeId, $defaults): int {
            $created = 0;

            foreach ($defaults as $index => $default) {
                $exists = ShiftTemplate::forStore($storeId)
                    ->where('template_name', $default['template_name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                ShiftTemplate::create([
                    ...$default,
                    'store_id' => $storeId,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
                $created++;
            }

            return $created;
        });
    }
}
