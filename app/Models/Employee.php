<?php

namespace App\Models;

use App\Helpers\StringHelpers;
use App\Traits\ScopedToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use ScopedToStore;

    public const TYPE_MANAGER = 'manager';

    public const TYPE_CREW = 'crew';

    protected $fillable = [
        'store_id',
        'employee_no',
        'first_name',
        'last_name',
        'middle_name',
        'employee_type',
        'manager_position_id',
        'primary_station_id',
        'employment_status',
        'date_hired',
        'contact_number',
        'max_hours_per_week',
        'user_id',
        'is_active',
        'is_team_leader',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
            'is_active' => 'boolean',
            'is_team_leader' => 'boolean',
            'manager_position_id' => 'integer',
            'primary_station_id' => 'integer',
            'max_hours_per_week' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function managerPosition(): BelongsTo
    {
        return $this->belongsTo(ManagerPosition::class, 'manager_position_id', 'position_id');
    }

    public function primaryStation(): BelongsTo
    {
        return $this->belongsTo(CrewStation::class, 'primary_station_id', 'station_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(EmployeeStation::class);
    }

    /** Stations this employee may be tagged to: cross-trained ones plus their primary. */
    public function trainedStationIds(): array
    {
        $stationIds = $this->stations->pluck('station_id')->all();

        if ($this->primary_station_id !== null) {
            $stationIds[] = $this->primary_station_id;
        }

        return array_values(array_unique(array_map('intval', $stationIds)));
    }

    /** One display name for the whole app — board rows, shift chips and rule warnings. */
    public function fullName(): string
    {
        return StringHelpers::fullName($this->first_name, $this->last_name);
    }

    public function isCrew(): bool
    {
        return $this->employee_type === self::TYPE_CREW;
    }

    public function isCrewTeamLeader(): bool
    {
        return $this->isCrew() && $this->is_team_leader;
    }
}
