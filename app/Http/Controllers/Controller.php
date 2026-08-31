<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 11+ no longer ships this on the base controller, but the schedule
    // controllers authorize row-level access through policies.
    use AuthorizesRequests;
}
