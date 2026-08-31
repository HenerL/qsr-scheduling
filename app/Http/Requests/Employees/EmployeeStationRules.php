<?php

namespace App\Http\Requests\Employees;

/**
 * Cross-training rows are validated identically whether they arrive with a full
 * employee save or through the dedicated stations endpoint.
 */
trait EmployeeStationRules
{
    protected function stationRules(): array
    {
        return [
            'stations' => ['nullable', 'array', 'max:50'],
            'stations.*.station_id' => ['required', 'integer', 'exists:crew_stations,station_id'],
            'stations.*.proficiency' => ['required', 'in:trainee,certified,trainer'],
        ];
    }

    protected function stationMessages(): array
    {
        return [
            'stations.*.station_id.required' => 'Each cross-training row needs a station.',
            'stations.*.station_id.exists' => 'One of the selected stations no longer exists.',
            'stations.*.proficiency.in' => 'Proficiency must be trainee, certified or trainer.',
        ];
    }
}
