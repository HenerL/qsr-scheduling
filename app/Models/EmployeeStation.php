<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStation extends Model
{
    protected $fillable = [
        'employee_id',
        'station_id',
        'proficiency',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(CrewStation::class, 'station_id', 'station_id');
    }
}
