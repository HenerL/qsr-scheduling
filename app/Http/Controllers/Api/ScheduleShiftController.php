<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedules\BulkTagShiftsRequest;
use App\Http\Requests\Schedules\SaveScheduleShiftRequest;
use App\Models\Schedule;
use App\Models\ScheduleShift;
use App\Services\ScheduleShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleShiftController extends Controller
{
    public function __construct(private readonly ScheduleShiftService $service)
    {
    }

    public function store(Schedule $schedule, SaveScheduleShiftRequest $request): JsonResponse
    {
        $this->authorize('update', $schedule);

        $result = $this->service->create($request->user(), $schedule, $request->validated());

        return QueryResultHelperV2::onSuccessCreate($result['shift'], warnings: $result['warnings']);
    }

    public function update(Schedule $schedule, ScheduleShift $shift, SaveScheduleShiftRequest $request): JsonResponse
    {
        $this->authorize('update', $schedule);

        $result = $this->service->update($request->user(), $schedule, $shift, $request->validated());

        return QueryResultHelperV2::onSuccessUpdate($result['shift'], warnings: $result['warnings']);
    }

    public function destroy(Schedule $schedule, ScheduleShift $shift, Request $request): JsonResponse
    {
        $this->authorize('update', $schedule);

        $this->service->delete($request->user(), $schedule, $shift);

        return QueryResultHelperV2::onSuccessDelete();
    }

    public function bulkStore(Schedule $schedule, BulkTagShiftsRequest $request): JsonResponse
    {
        $this->authorize('update', $schedule);

        $result = $this->service->bulkCreate($request->user(), $schedule, $request->validated()['shifts']);

        return QueryResultHelperV2::onSuccessCreate(
            ['count' => $result['count'], 'shifts' => $result['shifts']],
            warnings: $result['warnings'],
        );
    }
}
