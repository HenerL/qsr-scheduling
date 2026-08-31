<?php

namespace App\Repositories\Interfaces;

use App\Models\Store;
use App\Models\User;

interface StoreRepositoryInterface
{
    public function createForOwner(User $owner, array $data): Store;

    public function findByOwner(User $owner): ?Store;

    public function update(Store $store, array $data): void;

    public function advanceOnboardingStep(Store $store, int $minStep): void;

    public function getHours(Store $store): array;

    public function replaceHours(Store $store, array $days): void;
}
