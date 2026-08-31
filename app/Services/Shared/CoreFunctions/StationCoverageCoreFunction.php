<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Warn: a station staffed below `min_crew_required` on an open day.
 *
 * Coverage is evaluated per open date rather than per hour — a QSR manager reads the
 * board a day at a time, and an hourly matrix would warn on every shift changeover.
 */
class StationCoverageCoreFunction
{
    /**
     * @param  array  $countsByDateStation  ['2026-08-25' => [stationId => assignedCount]]
     * @param  array  $stations  [['station_id' => int, 'station_name' => string, 'min_crew_required' => ?int]]
     * @param  array  $openDates  Dates the store is open; closed days cannot be under-staffed.
     * @return array<int, array{date: string, station_id: int, station_name: string, required: int, assigned: int, message: string}>
     */
    public static function gaps(array $countsByDateStation, array $stations, array $openDates): array
    {
        $gaps = [];

        foreach ($openDates as $date) {
            foreach ($stations as $station) {
                $required = (int) ($station['min_crew_required'] ?? 0);

                if ($required <= 0) {
                    continue;
                }

                $stationId = (int) $station['station_id'];
                $assigned = (int) ($countsByDateStation[$date][$stationId] ?? 0);

                if ($assigned >= $required) {
                    continue;
                }

                $gaps[] = [
                    'date' => $date,
                    'station_id' => $stationId,
                    'station_name' => (string) $station['station_name'],
                    'required' => $required,
                    'assigned' => $assigned,
                    'message' => sprintf(
                        '%s has %d of %d required crew.',
                        $station['station_name'],
                        $assigned,
                        $required,
                    ),
                ];
            }
        }

        return $gaps;
    }
}
