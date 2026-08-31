<?php

namespace App\Mappers\CrewStations;

use App\Models\CrewStation;

class CrewStationMapper
{
    public static function map(CrewStation $station): array
    {
        return [
            'station_id' => $station->station_id,
            'store_id' => $station->store_id,
            'station_name' => $station->station_name,
            'station_description' => $station->station_description,
            'min_crew_required' => $station->min_crew_required,
            'sort_order' => $station->sort_order,
            'is_active' => $station->is_active,
        ];
    }
}
