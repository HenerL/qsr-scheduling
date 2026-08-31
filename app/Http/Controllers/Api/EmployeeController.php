<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\ListEmployeesRequest;
use App\Http\Requests\Employees\ProvisionCrewAccountRequest;
use App\Http\Requests\Employees\SaveEmployeeRequest;
use App\Http\Requests\Employees\SyncEmployeeStationsRequest;
use App\Services\EmployeeService;
use App\Services\PaginationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private const FILTERS = ['employee_type', 'employment_status', 'station_id', 'is_active'];

    public function __construct(private readonly EmployeeService $service)
    {
    }

    public function index(ListEmployeesRequest $request): JsonResponse
    {
        $result = $this->service->list($request->user(), PaginationService::params($request, self::FILTERS));

        return QueryResultHelperV2::onSuccessGet($result['rows'], meta: $result['meta']);
    }

    public function store(SaveEmployeeRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->service->create($request->user(), $request->validated()),
            'Employee created.',
        );
    }

    public function update(SaveEmployeeRequest $request, int $employeeId): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->service->update($request->user(), $employeeId, $request->validated()),
            'Employee updated.',
        );
    }

    public function destroy(Request $request, int $employeeId): JsonResponse
    {
        $this->service->deactivate($request->user(), $employeeId);

        return QueryResultHelperV2::onSuccessDelete('Employee deactivated.');
    }

    public function syncStations(SyncEmployeeStationsRequest $request, int $employeeId): JsonResponse
    {
        return QueryResultHelperV2::onSuccessUpdate(
            $this->service->syncStations($request->user(), $employeeId, $request->validated()['stations'] ?? []),
            'Cross-training updated.',
        );
    }

    public function provisionCrewAccount(ProvisionCrewAccountRequest $request, int $employeeId): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->service->provisionCrewAccount($request->user(), $employeeId, $request->validated()),
            'Crew login created.',
        );
    }
}
