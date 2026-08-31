<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Warn: crew should only be tagged to a station they are trained on.
 */
class StationEligibilityCoreFunction
{
    /**
     * @param  array  $trainedStationIds  Station ids the employee is trained on, primary station included.
     */
    public static function check(array $shift, array $trainedStationIds): ?string
    {
        $stationId = $shift['crew_station_id'] ?? null;

        if ($stationId === null || !empty($shift['is_rest_day'])) {
            return null;
        }

        $trained = array_map(static fn ($id) => (int) $id, $trainedStationIds);

        return in_array((int) $stationId, $trained, true)
            ? null
            : 'Not trained on the assigned station.';
    }
}
