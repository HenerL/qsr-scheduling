<?php

namespace App\Models;

use App\Traits\ScopedToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleShift extends Model
{
    use ScopedToStore;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'schedule_id',
        'store_id',
        'employee_id',
        'shift_date',
        'shift_template_id',
        'start_time',
        'end_time',
        'break_minutes',
        'crew_station_id',
        'manager_position_id',
        'is_rest_day',
        'status',
        'is_revised',
        'remarks',
        'created_by',
        'updated_by',
    ];

    // Times stay strings so TimeHelper receives `HH:MM:SS` untouched.
    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'break_minutes' => 'integer',
            'is_rest_day' => 'boolean',
            'is_revised' => 'boolean',
            'schedule_id' => 'integer',
            'employee_id' => 'integer',
            'shift_template_id' => 'integer',
            'crew_station_id' => 'integer',
            'manager_position_id' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(CrewStation::class, 'crew_station_id', 'station_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(ManagerPosition::class, 'manager_position_id', 'position_id');
    }

    public function isDuty(): bool
    {
        return !$this->is_rest_day && $this->status === self::STATUS_SCHEDULED;
    }
}
