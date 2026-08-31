<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class MasterfileRecordIdHelper
{
    public static function next(string $table, string $column, string $prefix, ?int $storeId = null, int $padding = 4): string
    {
        $query = DB::table($table)->where($column, 'like', $prefix . '-%');

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $highest = 0;
        foreach ($query->pluck($column) as $value) {
            $suffix = (int) substr((string) $value, strlen($prefix) + 1);
            $highest = max($highest, $suffix);
        }

        return $prefix . '-' . str_pad((string) ($highest + 1), $padding, '0', STR_PAD_LEFT);
    }
}
