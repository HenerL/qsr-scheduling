<?php

namespace App\Helpers;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleShift;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Team leaders share the board with managers but only for crew rows on drafts.
 * One abort site so ScheduleService and ScheduleShiftService cannot drift.
 */
class ScheduleBoardAccessHelper
{
    public static function isCrewScoped(User $user): bool
    {
        return $user->isCrewTeamLeader();
    }

    public static function assertCanMutate(User $user, Schedule $schedule): void
    {
        if (!self::isCrewScoped($user)) {
            return;
        }

        if ($schedule->isPublished()) {
            abort(403, 'Team leaders can only change draft weeks.');
        }
    }

    public static function assertCanTargetEmployee(User $user, ?Employee $employee): void
    {
        if (!self::isCrewScoped($user)) {
            return;
        }

        if ($employee === null || !$employee->isCrew()) {
            abort(403, 'Team leaders can only manage crew schedules.');
        }
    }

    /**
     * @param  Collection<int, ScheduleShift>  $shifts
     * @return Collection<int, ScheduleShift>
     */
    public static function crewShifts(User $user, Collection $shifts): Collection
    {
        if (!self::isCrewScoped($user)) {
            return $shifts;
        }

        return $shifts
            ->filter(static fn (ScheduleShift $shift) => $shift->employee !== null && $shift->employee->isCrew())
            ->values();
    }
}
