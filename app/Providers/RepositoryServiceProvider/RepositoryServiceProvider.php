<?php

namespace App\Providers\RepositoryServiceProvider;

use App\Repositories\AuthRepository;
use App\Repositories\CrewStationRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use App\Repositories\Interfaces\CrewStationRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\ManagerPositionRepositoryInterface;
use App\Repositories\Interfaces\ScheduleRepositoryInterface;
use App\Repositories\Interfaces\ScheduleShiftRepositoryInterface;
use App\Repositories\Interfaces\ShiftTemplateRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Repositories\ManagerPositionRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\ScheduleShiftRepository;
use App\Repositories\ShiftTemplateRepository;
use App\Repositories\StoreRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        AuthRepositoryInterface::class => AuthRepository::class,
        StoreRepositoryInterface::class => StoreRepository::class,
        ManagerPositionRepositoryInterface::class => ManagerPositionRepository::class,
        CrewStationRepositoryInterface::class => CrewStationRepository::class,
        EmployeeRepositoryInterface::class => EmployeeRepository::class,
        ShiftTemplateRepositoryInterface::class => ShiftTemplateRepository::class,
        ScheduleRepositoryInterface::class => ScheduleRepository::class,
        ScheduleShiftRepositoryInterface::class => ScheduleShiftRepository::class,
    ];
}
