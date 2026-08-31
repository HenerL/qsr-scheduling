<?php

namespace App\Services;

use App\Helpers\UserActivityHelper;
use App\Mappers\CrewStations\CrewStationMapper;
use App\Models\CrewStation;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\CrewStationRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Services\Shared\StoreContextService;

class CrewStationService
{
    private const DEFAULTS = [
        ['station_name' => 'Front Counter', 'station_description' => 'Order taking and cashiering.', 'min_crew_required' => 1],
        ['station_name' => 'Drive-Thru', 'station_description' => 'Order taking and hand-out at the drive-thru.', 'min_crew_required' => 2],
        ['station_name' => 'Grill', 'station_description' => 'Cooking patties and grilled items.', 'min_crew_required' => 1],
        ['station_name' => 'Fryer', 'station_description' => 'Fries, nuggets and fried products.', 'min_crew_required' => 1],
        ['station_name' => 'Beverage', 'station_description' => 'Drinks, desserts and sundae bar.', 'min_crew_required' => 1],
        ['station_name' => 'Dining/Lobby', 'station_description' => 'Table upkeep, cleanliness and guest assistance.', 'min_crew_required' => 1],
        ['station_name' => 'Dishwashing', 'station_description' => 'Washing trays, utensils and cookware.', 'min_crew_required' => 1],
    ];

    public function __construct(
        private readonly CrewStationRepositoryInterface $repository,
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
            static fn ($row) => CrewStationMapper::map($row),
        );
    }

    public function create(User $user, array $data): array
    {
        $store = $this->requireStore($user);
        $this->assertNameAvailable($store, $data['station_name']);

        $station = $this->repository->create($store->id, [
            'station_name' => $data['station_name'],
            'station_description' => $data['station_description'] ?? null,
            'min_crew_required' => $data['min_crew_required'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        UserActivityHelper::log('crew_stations', 'create', "Station '{$station->station_name}' created.", $station->station_id);

        $this->storeRepository->advanceOnboardingStep($store, 5);

        return CrewStationMapper::map($station);
    }

    public function update(User $user, int $stationId, array $data): array
    {
        $store = $this->requireStore($user);
        $station = $this->requireStation($store, $stationId);

        if (isset($data['station_name'])) {
            $this->assertNameAvailable($store, $data['station_name'], $stationId);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->repository->update($station, $data);

        UserActivityHelper::log('crew_stations', 'update', "Station '{$station->station_name}' updated.", $stationId);

        return CrewStationMapper::map($station->fresh() ?? $station);
    }

    public function deactivate(User $user, int $stationId): void
    {
        $store = $this->requireStore($user);
        $station = $this->requireStation($store, $stationId);

        $this->repository->deactivate($station);

        UserActivityHelper::log('crew_stations', 'deactivate', "Station '{$station->station_name}' deactivated.", $stationId);
    }

    public function seedDefaults(User $user): int
    {
        $store = $this->requireStore($user);
        $created = $this->repository->seedDefaults($store->id, self::DEFAULTS);
        $this->storeRepository->advanceOnboardingStep($store, 5);

        UserActivityHelper::log('crew_stations', 'seed_defaults', "Seeded {$created} default stations.", null);

        return $created;
    }

    private function assertNameAvailable(Store $store, string $name, ?int $exceptId = null): void
    {
        if ($this->repository->nameTaken($store->id, trim($name), $exceptId)) {
            abort(400, "A station named '{$name}' already exists in your store.");
        }
    }

    private function requireStore(User $user): Store
    {
        return $this->storeContext->requireForUser($user);
    }

    private function requireStation(Store $store, int $stationId): CrewStation
    {
        $station = $this->repository->findInStore($store->id, $stationId);

        if ($station === null) {
            abort(404, 'Station not found.');
        }

        return $station;
    }
}
