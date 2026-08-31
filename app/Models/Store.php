<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** Setup wizard is finished once the store reaches this step. */
    public const ONBOARDING_FINAL_STEP = 7;

    protected $fillable = [
        'owner_user_id',
        'store_name',
        'branch_name',
        'store_code',
        'address',
        'contact_number',
        'timezone',
        'week_starts_on',
        'max_consecutive_duty_days',
        'onboarding_step',
        'onboarding_completed_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_completed_at' => 'datetime',
            'is_active' => 'boolean',
            'week_starts_on' => 'integer',
            'max_consecutive_duty_days' => 'integer',
            'onboarding_step' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(StoreOperatingHour::class);
    }
}
