<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;

/**
 * A schedule belongs to exactly one store, so access is decided by the store the user
 * resolves to. The store is resolved the same way the controllers resolve it, so the
 * policy can never disagree with the rows a request is allowed to touch.
 *
 * Board routes allow managers and crew team leaders; publish stays manager-only.
 * Service-level checks still stop a team leader from touching manager rows or drafts.
 */
class SchedulePolicy
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return $this->belongsToStoreOf($user, $schedule);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->belongsToStoreOf($user, $schedule)
            && ($user->user_type === 'manager' || $user->isCrewTeamLeader());
    }

    public function publish(User $user, Schedule $schedule): bool
    {
        return $user->user_type === 'manager' && $this->belongsToStoreOf($user, $schedule);
    }

    private function belongsToStoreOf(User $user, Schedule $schedule): bool
    {
        $store = $this->storeRepository->findByOwner($user);

        return $store !== null && (int) $schedule->store_id === (int) $store->id;
    }
}
