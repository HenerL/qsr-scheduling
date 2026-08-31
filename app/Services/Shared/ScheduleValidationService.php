<?php

namespace App\Services\Shared;

use App\Helpers\DateHelper;
use App\Helpers\QueryResultHelperV2;
use App\Mappers\Schedules\ScheduleBoardMapper;
use App\Mappers\Schedules\ScheduleShiftMapper;
use App\Models\CrewStation;
use App\Models\Employee;
use App\Models\Store;
use App\Repositories\Interfaces\CrewStationRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\ScheduleShiftRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Services\Shared\CoreFunctions\ConsecutiveDutyDaysCoreFunction;
use App\Services\Shared\CoreFunctions\RestBetweenShiftsCoreFunction;
use App\Services\Shared\CoreFunctions\RestDayConflictCoreFunction;
use App\Services\Shared\CoreFunctions\ScheduleTotalsCoreFunction;
use App\Services\Shared\CoreFunctions\ShiftOverlapCoreFunction;
use App\Services\Shared\CoreFunctions\ShiftWithinOperatingHoursCoreFunction;
use App\Services\Shared\CoreFunctions\StationCoverageCoreFunction;
use App\Services\Shared\CoreFunctions\StationEligibilityCoreFunction;
use App\Services\Shared\CoreFunctions\WeeklyHoursCoreFunction;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The one place the schedule rules are assembled. Tagging, bulk tagging, copying and
 * publishing all run the same blocks and warnings through here, so a rule is never
 * enforced in two places with two different messages.
 *
 * Blocks come back as a field-keyed bag and stop the write. Warnings come back as a
 * list and ride along on the success envelope — the manager decides (PLAN §5).
 */
class ScheduleValidationService
{
    /** The consecutive-duty rule spans week ± 6 days, so a run can start in the previous week. */
    private const DUTY_LOOKBACK_DAYS = 6;

    /** One day either side is enough for an overnight neighbour's overlap and rest gap. */
    private const NEIGHBOUR_PADDING_DAYS = 1;

    public function __construct(
        private readonly ScheduleShiftRepositoryInterface $shiftRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly CrewStationRepositoryInterface $stationRepository,
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    public function makeContext(Store $store, string $weekStartDate): ScheduleValidationContext
    {
        $weekDates = DateHelper::weekDates($weekStartDate);
        $weekEndDate = $weekDates[count($weekDates) - 1];

        $employees = $this->employeeRepository->getActiveForStore($store->id);
        $employeeIds = $employees->pluck('id')->all();

        $daysByDate = [];
        foreach ($this->daysForDates($store, $weekDates) as $day) {
            $daysByDate[$day['date']] = $day;
        }

        return new ScheduleValidationContext(
            $daysByDate,
            $this->shiftsByEmployee($store, $employeeIds, $weekStartDate, $weekEndDate),
            $employeeIds === [] ? [] : $this->shiftRepository->getDutyDatesForEmployees(
                $store->id,
                $employeeIds,
                DateHelper::addDays($weekStartDate, -self::DUTY_LOOKBACK_DAYS),
                DateHelper::addDays($weekEndDate, self::DUTY_LOOKBACK_DAYS),
            ),
            $employees->keyBy('id')->all(),
            $this->stationRepository->getActiveForStore($store->id)
                ->map(static fn (CrewStation $station) => ScheduleBoardMapper::station($station))
                ->all(),
            (int) $store->max_consecutive_duty_days,
        );
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<int, array<string, mixed>>
     */
    public function daysForDates(Store $store, array $dates): array
    {
        $hoursByDayOfWeek = [];
        foreach ($this->storeRepository->getHours($store) as $hours) {
            $hoursByDayOfWeek[(int) $hours->day_of_week] = $hours;
        }

        return array_map(
            static fn (string $date) => ScheduleBoardMapper::day($date, $hoursByDayOfWeek),
            $dates,
        );
    }

    /**
     * @param  string  $fieldPrefix  'shifts.3.' for a bulk row, so the bag keys mirror the payload.
     * @return array<string, array<int, string>> Empty when the candidate is allowed.
     */
    public function blocks(array $candidate, ScheduleValidationContext $context, string $fieldPrefix = ''): array
    {
        $bag = [];
        $date = (string) $candidate['shift_date'];

        if (!$context->coversDate($date)) {
            // Every remaining rule reads this week's hours, so there is nothing left to judge.
            return [$fieldPrefix . 'shift_date' => ['Shift date must fall inside the schedule week.']];
        }

        $dayHours = $context->hoursForDate($date);
        $hoursMessage = ShiftWithinOperatingHoursCoreFunction::check($candidate, $dayHours);

        if ($hoursMessage !== null) {
            $field = empty($dayHours['is_open']) ? 'shift_date' : 'start_time';
            $bag[$fieldPrefix . $field][] = $hoursMessage;
        }

        $existing = $context->shiftsFor((int) $candidate['employee_id']);
        $overlap = ShiftOverlapCoreFunction::firstOverlap($candidate, $existing);

        if ($overlap !== null) {
            $bag[$fieldPrefix . 'start_time'][] = sprintf(
                'Overlaps an existing %s–%s shift on %s.',
                $overlap['start_time'],
                $overlap['end_time'],
                $overlap['shift_date'],
            );
        }

        $restDayMessage = RestDayConflictCoreFunction::check($candidate, $existing);

        if ($restDayMessage !== null) {
            $field = empty($candidate['is_rest_day']) ? 'shift_date' : 'is_rest_day';
            $bag[$fieldPrefix . $field][] = $restDayMessage;
        }

        return $bag;
    }

    /**
     * Warnings for one shift. The shift must already be remembered on the context —
     * the weekly total and the duty run describe the state after the write, not before.
     */
    public function shiftWarnings(array $shift, ScheduleValidationContext $context): array
    {
        $employeeId = (int) $shift['employee_id'];
        $employee = $context->employee($employeeId);
        $hours = ScheduleTotalsCoreFunction::hoursByEmployee($context->shiftsInWeek())[$employeeId] ?? 0.0;

        return $this->dedupe([
            $this->eligibilityWarning($shift, $employee),
            $this->restWarning($shift, $context),
            $this->consecutiveDutyWarning($shift['shift_date'], $employee, $context),
            $this->weeklyHoursWarning($employee, $hours),
        ]);
    }

    /**
     * Every warning in the week, consolidated for the publish dialog and the summary.
     */
    public function weekWarnings(ScheduleValidationContext $context): array
    {
        $weekShifts = $context->shiftsInWeek();
        $hoursByEmployee = ScheduleTotalsCoreFunction::hoursByEmployee($weekShifts);
        $warnings = [];

        foreach ($context->employees() as $employeeId => $employee) {
            $warnings[] = $this->weeklyHoursWarning($employee, $hoursByEmployee[$employeeId] ?? 0.0);

            foreach ($context->weekDates() as $date) {
                $warnings[] = $this->consecutiveDutyWarning($date, $employee, $context);
            }
        }

        foreach ($weekShifts as $shift) {
            $employee = $context->employee((int) $shift['employee_id']);
            $warnings[] = $this->eligibilityWarning($shift, $employee);
            $warnings[] = $this->restWarning($shift, $context);
        }

        foreach (StationCoverageCoreFunction::gaps(
            ScheduleTotalsCoreFunction::stationCountsByDate($weekShifts),
            $context->stations(),
            $context->openDates(),
        ) as $gap) {
            $warnings[] = [
                'rule' => 'station_coverage',
                'message' => $gap['message'],
                'employee_id' => null,
                'employee_name' => null,
                'shift_date' => $gap['date'],
            ];
        }

        return $this->dedupe($warnings);
    }

    /**
     * The single throw site for a blocked write. Mirrors BaseFormRequest::failedValidation
     * so a rule break and a validation failure look identical to the frontend.
     */
    public function assertNotBlocked(array $blocks, string $message = 'This schedule change breaks a rule.'): void
    {
        if ($blocks === []) {
            return;
        }

        throw new HttpResponseException(QueryResultHelperV2::onBadRequest($blocks, $message));
    }

    private function shiftsByEmployee(Store $store, array $employeeIds, string $weekStartDate, string $weekEndDate): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $shifts = $this->shiftRepository->getForEmployeeDates(
            $store->id,
            $employeeIds,
            DateHelper::addDays($weekStartDate, -self::NEIGHBOUR_PADDING_DAYS),
            DateHelper::addDays($weekEndDate, self::NEIGHBOUR_PADDING_DAYS),
        );

        $grouped = [];
        foreach ($shifts as $shift) {
            $grouped[(int) $shift->employee_id][] = ScheduleShiftMapper::map($shift);
        }

        return $grouped;
    }

    private function eligibilityWarning(array $shift, ?Employee $employee): ?array
    {
        if ($employee === null || !$employee->isCrew()) {
            return null;
        }

        return $this->warning(
            'station_eligibility',
            StationEligibilityCoreFunction::check($shift, $employee->trainedStationIds()),
            $employee,
            $shift['shift_date'],
        );
    }

    private function restWarning(array $shift, ScheduleValidationContext $context): ?array
    {
        $employeeId = (int) $shift['employee_id'];

        return $this->warning(
            'rest_between_shifts',
            RestBetweenShiftsCoreFunction::check($shift, $context->shiftsFor($employeeId)),
            $context->employee($employeeId),
            $shift['shift_date'],
        );
    }

    private function consecutiveDutyWarning(string $date, ?Employee $employee, ScheduleValidationContext $context): ?array
    {
        if ($employee === null) {
            return null;
        }

        $limit = $context->maxConsecutiveDutyDays();
        $run = ConsecutiveDutyDaysCoreFunction::longestRun($context->dutyDatesFor((int) $employee->id), $date);

        if (!ConsecutiveDutyDaysCoreFunction::exceedsLimit($run, $limit)) {
            return null;
        }

        return $this->warning(
            'consecutive_duty_days',
            ConsecutiveDutyDaysCoreFunction::message($run, $limit),
            $employee,
            $date,
        );
    }

    private function weeklyHoursWarning(?Employee $employee, float $hours): ?array
    {
        if ($employee === null) {
            return null;
        }

        return $this->warning(
            'weekly_hours',
            WeeklyHoursCoreFunction::check($hours, $employee->max_hours_per_week),
            $employee,
            null,
        );
    }

    private function warning(string $rule, ?string $message, ?Employee $employee, ?string $date): ?array
    {
        if ($message === null) {
            return null;
        }

        return [
            'rule' => $rule,
            'message' => $message,
            'employee_id' => $employee?->id,
            'employee_name' => $employee?->fullName(),
            'shift_date' => $date,
        ];
    }

    /**
     * The rest-gap rule reports the same pair from both shifts, and a duty run repeats
     * on every date it covers, so the same warning is raised more than once by design.
     */
    private function dedupe(array $warnings): array
    {
        $unique = [];

        foreach (array_filter($warnings) as $warning) {
            $key = implode('|', [
                $warning['rule'],
                (string) $warning['employee_id'],
                (string) $warning['shift_date'],
                $warning['message'],
            ]);

            $unique[$key] ??= $warning;
        }

        return array_values($unique);
    }
}
