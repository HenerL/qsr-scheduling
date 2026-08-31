<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'module',
        'action',
        'record_id',
        'description',
        'ip_address',
    ];
}
