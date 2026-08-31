<?php

namespace App\Models;

use App\Traits\ScopedToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewStation extends Model
{
    use ScopedToStore;

    protected $primaryKey = 'station_id';

    protected $fillable = [
        'store_id',
        'station_name',
        'station_description',
        'min_crew_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'min_crew_required' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
