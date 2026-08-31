<?php

namespace App\Services\Shared;

use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;

class StoreContextService
{
    public function __construct(private readonly StoreRepositoryInterface $storeRepository)
    {
    }

    public function requireForUser(User $user): Store
    {
        $store = $this->storeRepository->findByOwner($user);

        if ($store === null) {
            abort(404, 'No store found for this account yet.');
        }

        return $store;
    }
}
