<?php

namespace App\Services;

use App\Helpers\UserActivityHelper;
use App\Mappers\ManagerPositions\ManagerPositionMapper;
use App\Models\ManagerPosition;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\ManagerPositionRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Services\Shared\StoreContextService;

class ManagerPositionService
{
    private const DEFAULTS = [
        ['position_name' => 'Store Manager', 'position_description' => 'Overall store operations and staffing.'],
        ['position_name' => 'Assistant Manager', 'position_description' => 'Supports the store manager; runs shifts.'],
        ['position_name' => 'Shift Manager', 'position_description' => 'Leads a shift and the crew on duty.'],
        ['position_name' => 'Manager Trainee', 'position_description' => 'In training for a management role.'],
    ];

    public function __construct(
        private readonly ManagerPositionRepositoryInterface $repository,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreContextService $storeContext,
    ) {
    }

    public function list(User $user, array $params): array
    {
        $store = $this->requireStore($user);
        $paginator = $this->repository->getPaginated($store->id, $params);

        return PaginationService::mapPaginator(
            $paginator,
            static fn ($row) => ManagerPositionMapper::map($row),
        );
    }

    public function create(User $user, array $data): array
    {
        $store = $this->requireStore($user);
        $this->assertNameAvailable($store, $data['position_name']);

        $position = $this->repository->create($store->id, [
            'position_name' => $data['position_name'],
            'position_description' => $data['position_description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        UserActivityHelper::log('manager_positions', 'create', "Position '{$position->position_name}' created.", $position->position_id);

        $this->storeRepository->advanceOnboardingStep($store, 4);

        return ManagerPositionMapper::map($position);
    }

    public function update(User $user, int $positionId, array $data): array
    {
        $store = $this->requireStore($user);
        $position = $this->requirePosition($store, $positionId);

        if (isset($data['position_name'])) {
            $this->assertNameAvailable($store, $data['position_name'], $positionId);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->repository->update($position, $data);

        UserActivityHelper::log('manager_positions', 'update', "Position '{$position->position_name}' updated.", $positionId);

        return ManagerPositionMapper::map($position->fresh() ?? $position);
    }

    public function deactivate(User $user, int $positionId): void
    {
        $store = $this->requireStore($user);
        $position = $this->requirePosition($store, $positionId);

        $this->repository->deactivate($position);

        UserActivityHelper::log('manager_positions', 'deactivate', "Position '{$position->position_name}' deactivated.", $positionId);
    }

    public function seedDefaults(User $user): int
    {
        $store = $this->requireStore($user);
        $created = $this->repository->seedDefaults($store->id, self::DEFAULTS);
        $this->storeRepository->advanceOnboardingStep($store, 4);

        UserActivityHelper::log('manager_positions', 'seed_defaults', "Seeded {$created} default positions.", null);

        return $created;
    }

    private function assertNameAvailable(Store $store, string $name, ?int $exceptId = null): void
    {
        if ($this->repository->nameTaken($store->id, trim($name), $exceptId)) {
            abort(400, "A position named '{$name}' already exists in your store.");
        }
    }

    private function requireStore(User $user): Store
    {
        return $this->storeContext->requireForUser($user);
    }

    private function requirePosition(Store $store, int $positionId): ManagerPosition
    {
        $position = $this->repository->findInStore($store->id, $positionId);

        if ($position === null) {
            abort(404, 'Position not found.');
        }

        return $position;
    }
}
