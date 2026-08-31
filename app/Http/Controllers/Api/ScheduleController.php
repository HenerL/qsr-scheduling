<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedules\CopyWeekRequest;
use App\Http\Requests\Schedules\ShowScheduleMonthRequest;
use App\Http\Requests\Schedules\ShowScheduleWeekRequest;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $service)
    {
    }

    public function show(ShowScheduleWeekRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessGet(
            $this->service->week($request->user(), $request->validated('week_start_date')),
        );
    }

    public function month(ShowScheduleMonthRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessGet(
            $this->service->month($request->user(), $request->validated('month')),
        );
    }

    public function copyFrom(Schedule $schedule, CopyWeekRequest $request): JsonResponse
    {
        $this->authorize('update', $schedule);

        return QueryResultHelperV2::onSuccessCreate(
            $this->service->copyFrom(
                $request->user(),
                $schedule,
                $request->validated('source_week_start_date'),
            ),
            'Week copied.',
        );
    }

    public function publish(Schedule $schedule, Request $request): JsonResponse
    {
        $this->authorize('publish', $schedule);

        $result = $this->service->publish($request->user(), $schedule);

        return QueryResultHelperV2::onSuccessGet(
            ['schedule' => $result['schedule']],
            warnings: $result['warnings'],
        );
    }

    public function summary(Schedule $schedule, Request $request): JsonResponse
    {
        $this->authorize('view', $schedule);

        return QueryResultHelperV2::onSuccessGet(
            $this->service->summary($request->user(), $schedule),
        );
    }

    public function crewMySchedule(Request $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessGet(
            $this->service->crewMySchedule($request->user(), $request->query('week_start_date')),
        );
    }
}
