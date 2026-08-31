<?php

namespace App\Services;

use App\Helpers\UserActivityHelper;
use App\Mappers\Store\StoreMapper;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;

class StoreService
{
    public function __construct(private readonly StoreRepositoryInterface $storeRepository)
    {
    }

    public function createForOwner(User $user, array $data): array
    {
        if ($this->storeRepository->findByOwner($user) !== null) {
            abort(400, 'You already have a store.');
        }

        $store = $this->storeRepository->createForOwner($user, $data);

        UserActivityHelper::log('store', 'create', "Store '{$store->store_name}' created.", $store->id);

        return StoreMapper::map($store);
    }

    public function getProfile(User $user): array
    {
        $store = $this->requireStore($user);

        return StoreMapper::map($store);
    }

    public function updateProfile(User $user, array $data): array
    {
        $store = $this->requireStore($user);

        $this->storeRepository->update($store, $data);

        UserActivityHelper::log('store', 'update_profile', 'Store profile updated.', $store->id);

        return StoreMapper::map($store->fresh() ?? $store);
    }

    public function getOperatingHours(User $user): array
    {
        $store = $this->requireStore($user);

        return StoreMapper::mapHoursCollection(collect($this->storeRepository->getHours($store)));
    }

    public function replaceOperatingHours(User $user, array $days): array
    {
        $store = $this->requireStore($user);

        $this->assertCompleteWeek($days);

        $this->storeRepository->replaceHours($store, $days);

        $this->storeRepository->advanceOnboardingStep($store, 3);

        UserActivityHelper::log('store', 'update_hours', 'Operating hours updated.', $store->id);

        return [
            'hours' => StoreMapper::mapHoursCollection(collect($this->storeRepository->getHours($store))),
            'onboarding_step' => $store->onboarding_step,
        ];
    }

    private function requireStore(User $user): Store
    {
        $store = $user->store_id !== null ? Store::find($user->store_id) : null;

        if ($store === null) {
            abort(404, 'No store found for this account yet.');
        }

        return $store;
    }

    private function assertCompleteWeek(array $days): void
    {
        $provided = array_map(static fn (array $day) => (int) $day['day_of_week'], $days);

        if (count($provided) !== 7 || count(array_unique($provided)) !== 7) {
            abort(400, 'Operating hours must cover all 7 days exactly once.');
        }
    }
}
