<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait ScopedToStore
{
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where($this->qualifyColumn('store_id'), $storeId);
    }
}
