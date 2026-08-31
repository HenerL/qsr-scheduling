<?php

namespace App\Services;

use App\Helpers\UserActivityHelper;
use App\Mappers\Employees\EmployeeMapper;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use App\Repositories\Interfaces\CrewStationRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\ManagerPositionRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Services\Shared\StoreContextService;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    private const DEFAULT_WEEKLY_HOURS = [
        'full_time' => 40,
        'part_time' => 24,
        'trainee' => 24,
    ];

    private const PRIMARY_STATION_PROFICIENCY = 'certified';

    public function __construct(
        private readonly EmployeeRepositoryInterface $repository,
        private readonly ManagerPositionRepositoryInterface $positionRepository,
        private readonly CrewStationRepositoryInterface $stationRepository,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly AuthRepositoryInterface $authRepository,
        private readonly StoreContextService $storeContext,
    ) {
    }

    public function list(User $user, array $params): array
    {
        $store = $this->storeContext->requireForUser($user);
        $paginator = $this->repository->getPaginated($store->id, $params);

        return PaginationService::mapPaginator(
            $paginator,
            static fn ($row) => EmployeeMapper::map($row),
        );
    }

    public function create(User $user, array $data): array
    {
        $store = $this->storeContext->requireForUser($user);
        $attributes = $this->resolveAttributes($store, $data);
        $stations = $this->resolveStations($store, $data['stations'] ?? [], $attributes['primary_station_id']);

        $employee = DB::transaction(function () use ($store, $attributes, $stations): Employee {
            $created = $this->repository->create($store->id, $attributes);
            $this->repository->syncStations($created, $stations);

            return $created;
        });

        UserActivityHelper::log('employees', 'create', "Employee '{$employee->employee_no}' created.", $employee->id);

        $this->storeRepository->advanceOnboardingStep($store, 6);

        return EmployeeMapper::map($this->requireEmployee($store, $employee->id));
    }

    public function update(User $user, int $employeeId, array $data): array
    {
        $store = $this->storeContext->requireForUser($user);
        $employee = $this->requireEmployee($store, $employeeId);

        $attributes = $this->resolveAttributes($store, $data);
        $stations = $this->resolveStations($store, $data['stations'] ?? [], $attributes['primary_station_id']);

        DB::transaction(function () use ($employee, $attributes, $stations): void {
            $this->repository->update($employee, $attributes);
            $this->repository->syncStations($employee, $stations);
        });

        UserActivityHelper::log('employees', 'update', "Employee '{$employee->employee_no}' updated.", $employeeId);

        return EmployeeMapper::map($this->requireEmployee($store, $employeeId));
    }

    public function deactivate(User $user, int $employeeId): void
    {
        $store = $this->storeContext->requireForUser($user);
        $employee = $this->requireEmployee($store, $employeeId);

        DB::transaction(function () use ($employee): void {
            $this->repository->deactivate($employee);

            if ($employee->user !== null) {
                $this->authRepository->deactivate($employee->user);
            }
        });

        UserActivityHelper::log('employees', 'deactivate', "Employee '{$employee->employee_no}' deactivated.", $employeeId);
    }

    public function provisionCrewAccount(User $user, int $employeeId, array $data): array
    {
        $store = $this->storeContext->requireForUser($user);
        $employee = $this->requireEmployee($store, $employeeId);

        if (!$employee->isCrew()) {
            abort(400, 'Only crew members can receive a crew login.');
        }

        if ($employee->user_id !== null) {
            abort(400, 'This employee already has a login.');
        }

        DB::transaction(function () use ($store, $employee, $data): void {
            $crewUser = $this->authRepository->createCrew([
                'name' => $employee->fullName(),
                'email' => $data['email'],
                'password' => $data['password'],
                'store_id' => $store->id,
            ]);

            $this->repository->update($employee, ['user_id' => $crewUser->id]);
        });

        UserActivityHelper::log(
            'employees',
            'provision_crew_account',
            "Crew login created for '{$employee->employee_no}'.",
            $employeeId,
        );

        return EmployeeMapper::map($this->requireEmployee($store, $employeeId));
    }

    public function syncStations(User $user, int $employeeId, array $stations): array
    {
        $store = $this->storeContext->requireForUser($user);
        $employee = $this->requireEmployee($store, $employeeId);

        $resolved = $this->resolveStations($store, $stations, $employee->primary_station_id);

        DB::transaction(fn () => $this->repository->syncStations($employee, $resolved));

        UserActivityHelper::log(
            'employees',
            'sync_stations',
            "Cross-training updated for '{$employee->employee_no}' (" . count($resolved) . ' station(s)).',
            $employeeId,
        );

        return EmployeeMapper::map($this->requireEmployee($store, $employeeId));
    }

    /**
     * Manager and crew are mutually exclusive: a manager carries a position and no
     * station, a crew member carries a primary station and no position.
     */
    private function resolveAttributes(Store $store, array $data): array
    {
        $isManager = $data['employee_type'] === 'manager';
        $positionId = $isManager ? (int) $data['manager_position_id'] : null;
        $stationId = $isManager ? null : (int) $data['primary_station_id'];

        if ($positionId !== null) {
            $this->requirePosition($store, $positionId);
        }

        if ($stationId !== null) {
            $this->requireStation($store, $stationId);
        }

        $employmentStatus = $data['employment_status'];

        return [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'middle_name' => $this->nullableTrim($data['middle_name'] ?? null),
            'employee_type' => $data['employee_type'],
            'manager_position_id' => $positionId,
            'primary_station_id' => $stationId,
            'employment_status' => $employmentStatus,
            'date_hired' => $data['date_hired'],
            'contact_number' => $this->nullableTrim($data['contact_number'] ?? null),
            'max_hours_per_week' => $data['max_hours_per_week'] ?? self::DEFAULT_WEEKLY_HOURS[$employmentStatus],
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_team_leader' => $isManager
                ? false
                : filter_var($data['is_team_leader'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Keeps the cross-training matrix consistent: every station must belong to this
     * store, and a crew member is always certified on their own primary station.
     */
    private function resolveStations(Store $store, array $stations, ?int $primaryStationId): array
    {
        $byStationId = [];

        foreach ($stations as $station) {
            $stationId = (int) ($station['station_id'] ?? 0);

            if ($stationId === 0) {
                continue;
            }

            $byStationId[$stationId] = [
                'station_id' => $stationId,
                'proficiency' => $station['proficiency'] ?? 'trainee',
            ];
        }

        if ($primaryStationId !== null && !isset($byStationId[$primaryStationId])) {
            $byStationId[$primaryStationId] = [
                'station_id' => $primaryStationId,
                'proficiency' => self::PRIMARY_STATION_PROFICIENCY,
            ];
        }

        $this->assertStationsInStore($store, array_keys($byStationId));

        return array_values($byStationId);
    }

    private function assertStationsInStore(Store $store, array $stationIds): void
    {
        if ($stationIds === []) {
            return;
        }

        $existing = $this->stationRepository->existingIdsInStore($store->id, $stationIds);
        $missing = array_diff($stationIds, $existing);

        if ($missing !== []) {
            abort(404, 'One or more selected stations do not belong to your store.');
        }
    }

    private function requireEmployee(Store $store, int $employeeId): Employee
    {
        $employee = $this->repository->findInStore($store->id, $employeeId);

        if ($employee === null) {
            abort(404, 'Employee not found.');
        }

        return $employee;
    }

    private function requirePosition(Store $store, int $positionId): void
    {
        if ($this->positionRepository->findInStore($store->id, $positionId) === null) {
            abort(404, 'Manager position not found.');
        }
    }

    private function requireStation(Store $store, int $stationId): void
    {
        if ($this->stationRepository->findInStore($store->id, $stationId) === null) {
            abort(404, 'Crew station not found.');
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = $value === null ? '' : trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
