<?php

namespace App\Models;

use App\Traits\ScopedToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTemplate extends Model
{
    use ScopedToStore;

    protected $fillable = [
        'store_id',
        'template_name',
        'template_code',
        'start_time',
        'end_time',
        'break_minutes',
        'applies_to',
        'color_hex',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function appliesToCrew(): bool
    {
        return $this->applies_to !== 'manager';
    }
}
