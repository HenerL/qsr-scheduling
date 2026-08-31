<?php

namespace App\Repositories;

use App\Helpers\MasterfileRecordIdHelper;
use App\Models\Employee;
use App\Models\EmployeeStation;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    private const SORTABLE = ['employee_no', 'first_name', 'last_name', 'date_hired', 'created_at'];

    private const RELATIONS = ['managerPosition', 'primaryStation', 'stations.station', 'user'];

    public function getPaginated(int $storeId, array $params): LengthAwarePaginator
    {
        $query = Employee::forStore($storeId)->with(self::RELATIONS);

        if (!empty($params['search'])) {
            $search = '%' . $params['search'] . '%';
            $query->where(static function (Builder $builder) use ($search): void {
                $builder->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('employee_no', 'like', $search);
            });
        }

        if (!empty($params['employee_type'])) {
            $query->where('employee_type', $params['employee_type']);
        }

        if (!empty($params['employment_status'])) {
            $query->where('employment_status', $params['employment_status']);
        }

        if (!empty($params['station_id'])) {
            $stationId = (int) $params['station_id'];
            $query->where(static function (Builder $builder) use ($stationId): void {
                $builder->where('primary_station_id', $stationId)
                    ->orWhereHas('stations', static fn (Builder $stations) => $stations->where('station_id', $stationId));
            });
        }

        if (isset($params['is_active']) && $params['is_active'] !== null && $params['is_active'] !== '') {
            $query->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = in_array($params['sort_by'] ?? null, self::SORTABLE, true)
            ? $params['sort_by']
            : 'last_name';

        return $query
            ->orderBy($sortBy, $params['sort_dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate(
                perPage: $params['per_page'] ?? 15,
                page: $params['page'] ?? 1,
            );
    }

    public function getActiveForStore(int $storeId): Collection
    {
        return Employee::forStore($storeId)
            ->with(self::RELATIONS)
            ->where('is_active', true)
            ->orderByRaw("employee_type = ? DESC", ['manager'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function findInStore(int $storeId, int $employeeId): ?Employee
    {
        return Employee::forStore($storeId)->with(self::RELATIONS)->find($employeeId);
    }
    public function create(int $storeId, array $data): Employee
    {
        $employee = Employee::create([
            ...$data,
            'store_id' => $storeId,
            'employee_no' => MasterfileRecordIdHelper::next('employees', 'employee_no', 'EMP', $storeId),
        ]);

        return $employee->load(self::RELATIONS);
    }

    public function update(Employee $employee, array $data): void
    {
        $employee->fill($data)->save();
    }

    public function deactivate(Employee $employee): void
    {
        $employee->fill(['is_active' => false])->save();
    }

    public function syncStations(Employee $employee, array $stations): void
    {
        $keepStationIds = array_column($stations, 'station_id');

        EmployeeStation::where('employee_id', $employee->id)
            ->when($keepStationIds !== [], static fn ($query) => $query->whereNotIn('station_id', $keepStationIds))
            ->delete();

        foreach ($stations as $station) {
            EmployeeStation::updateOrCreate(
                ['employee_id' => $employee->id, 'station_id' => $station['station_id']],
                ['proficiency' => $station['proficiency']],
            );
        }
    }

    public function findByUserId(int $userId): ?Employee
    {
        return Employee::where('user_id', $userId)->first();
    }
}
