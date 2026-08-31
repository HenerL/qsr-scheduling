<?php

namespace App\Mappers\ManagerPositions;

use App\Models\ManagerPosition;

class ManagerPositionMapper
{
    public static function map(ManagerPosition $position): array
    {
        return [
            'position_id' => $position->position_id,
            'store_id' => $position->store_id,
            'position_name' => $position->position_name,
            'position_description' => $position->position_description,
            'sort_order' => $position->sort_order,
            'is_active' => $position->is_active,
        ];
    }
}
