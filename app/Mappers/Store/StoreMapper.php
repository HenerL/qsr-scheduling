<?php

namespace App\Mappers\Store;

use App\Models\Store;
use App\Models\StoreOperatingHour;

class StoreMapper
{
    public static function map(Store $store): array
    {
        return [
            'id' => $store->id,
            'store_name' => $store->store_name,
            'branch_name' => $store->branch_name,
            'store_code' => $store->store_code,
            'address' => $store->address,
            'contact_number' => $store->contact_number,
            'timezone' => $store->timezone,
            'week_starts_on' => $store->week_starts_on,
            'max_consecutive_duty_days' => $store->max_consecutive_duty_days,
            'onboarding_step' => $store->onboarding_step,
            'onboarding_completed_at' => $store->onboarding_completed_at?->toISOString(),
        ];
    }

    public static function mapHours(StoreOperatingHour $hour): array
    {
        return [
            'day_of_week' => $hour->day_of_week,
            'is_open' => $hour->is_open,
            'is_24_hours' => $hour->is_24_hours,
            'open_time' => $hour->open_time !== null ? substr($hour->open_time, 0, 5) : null,
            'close_time' => $hour->close_time !== null ? substr($hour->close_time, 0, 5) : null,
        ];
    }

    public static function mapHoursCollection($hours): array
    {
        return $hours
            ->map(static fn (StoreOperatingHour $hour) => self::mapHours($hour))
            ->values()
            ->all();
    }
}
