<?php

namespace App\Helpers;

use App\Models\UserActivityLog;

class UserActivityHelper
{
    public static function log(string $module, string $action, string $description, ?int $recordId = null): void
    {
        $user = auth()->user();

        UserActivityLog::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'store_id' => $user?->store_id,
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => request()?->ip(),
        ]);
    }
}
