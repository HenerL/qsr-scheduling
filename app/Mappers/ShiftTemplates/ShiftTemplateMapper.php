<?php

namespace App\Mappers\ShiftTemplates;

use App\Helpers\TimeHelper;
use App\Models\ShiftTemplate;

class ShiftTemplateMapper
{
    public static function map(ShiftTemplate $template): array
    {
        $start = substr((string) $template->start_time, 0, 5);
        $end = substr((string) $template->end_time, 0, 5);
        $breakMinutes = (int) $template->break_minutes;

        return [
            'id' => $template->id,
            'store_id' => $template->store_id,
            'template_name' => $template->template_name,
            'template_code' => $template->template_code,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => $breakMinutes,
            'applies_to' => $template->applies_to,
            'color_hex' => $template->color_hex,
            'sort_order' => $template->sort_order,
            'is_active' => $template->is_active,
            // Derived on read so the stored row stays the single source of truth.
            'total_hours' => TimeHelper::netHours($start, $end, $breakMinutes),
            'crosses_midnight' => TimeHelper::crossesMidnight($start, $end),
        ];
    }
}
