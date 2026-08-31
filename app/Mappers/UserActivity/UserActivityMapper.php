<?php

namespace App\Mappers\UserActivity;

use App\Models\UserActivityLog;

class UserActivityMapper
{
    public static function map(UserActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'store_id' => $log->store_id,
            'module' => $log->module,
            'action' => $log->action,
            'record_id' => $log->record_id,
            'description' => $log->description,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}
