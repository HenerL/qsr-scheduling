<?php

namespace App\Models;

use App\Traits\ScopedToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use ScopedToStore;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'store_id',
        'week_start_date',
        'status',
        'published_at',
        'published_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'published_at' => 'datetime',
            'published_by' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(ScheduleShift::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
