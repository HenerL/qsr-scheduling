<?php

namespace App\Services;

use App\Helpers\ScheduleBoardAccessHelper;
use App\Helpers\UserActivityHelper;
use App\Mappers\Schedules\ScheduleShiftMapper;
use App\Models\Schedule;
use App\Models\ScheduleShift;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\ScheduleShiftRepositoryInterface;
use App\Repositories\Interfaces\ShiftTemplateRepositoryInterface;
use App\Services\Shared\ScheduleValidationService;
use App\Services\Shared\StoreContextService;
use Illuminate\Support\Facades\DB;

class ScheduleShiftService
{
    public function __construct(
        private readonly ScheduleShiftRepositoryInterface $shiftRepository,
        private readonly ShiftTemplateRepositoryInterface $templateRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly ScheduleValidationService $validation,
        private readonly StoreContextService $storeContext,
    ) {
    }

    /**
     * @return array{shift: array, warnings: array}
     */
    public function create(User $user, Schedule $schedule, array $data): array
    {
        ScheduleBoardAccessHelper::assertCanMutate($user, $schedule);

        $store = $this->storeContext->requireForUser($user);
        $employee = $this->employeeRepository->findInStore($store->id, (int) $data['employee_id']);
        if ($employee === null) {
            $this->validation->assertNotBlocked(['employee_id' => ['Employee does not belong to this store.']]);
        }
        ScheduleBoardAccessHelper::assertCanTargetEmployee($user, $employee);

        $candidate = $this->candidate($store->id, $schedule->id, $data);
        $context = $this->validation->makeContext($store, $schedule->week_start_date->toDateString());
        $this->validation->assertNotBlocked($this->validation->blocks($candidate, $context));

        $shift = $this->shiftRepository->create([
            ...$candidate,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        UserActivityHelper::log('schedules', 'tag_shift', "Shift tagged for {$shift->shift_date->toDateString()}.", $shift->id);

        return $this->mappedWithWarnings($store, $schedule, $shift);
    }

    /**
     * @return array{shift: array, warnings: array}
     */
    public function update(User $user, Schedule $schedule, ScheduleShift $shift, array $data): array
    {
        ScheduleBoardAccessHelper::assertCanMutate($user, $schedule);

        $store = $this->storeContext->requireForUser($user);
        $this->assertShiftOnSchedule($store, $schedule, $shift);
        ScheduleBoardAccessHelper::assertCanTargetEmployee($user, $shift->employee);

        $candidate = $this->candidate($store->id, $schedule->id, [
            ...$data,
            'employee_id' => $shift->employee_id,
        ]);
        $candidate['id'] = $shift->id;

        $context = $this->validation->makeContext($store, $schedule->week_start_date->toDateString());
        $this->validation->assertNotBlocked($this->validation->blocks($candidate, $context));

        $this->shiftRepository->update($shift, [
            ...$candidate,
            'updated_by' => $user->id,
            'is_revised' => $schedule->isPublished() ? true : $shift->is_revised,
        ]);

        if ($schedule->isPublished()) {
            UserActivityHelper::log(
                'schedules',
                'revise_shift',
                "Published shift {$shift->id} revised.",
                $shift->id,
            );
        } else {
            UserActivityHelper::log('schedules', 'update_shift', "Shift {$shift->id} updated.", $shift->id);
        }

        return $this->mappedWithWarnings($store, $schedule, $shift);
    }

    public function delete(User $user, Schedule $schedule, ScheduleShift $shift): void
    {
        ScheduleBoardAccessHelper::assertCanMutate($user, $schedule);

        $store = $this->storeContext->requireForUser($user);
        $this->assertShiftOnSchedule($store, $schedule, $shift);
        ScheduleBoardAccessHelper::assertCanTargetEmployee($user, $shift->employee);

        $shiftId = $shift->id;
        $this->shiftRepository->delete($shift);

        UserActivityHelper::log('schedules', 'delete_shift', "Shift {$shiftId} removed.", $shiftId);
    }

    /**
     * @return array{count: int, shifts: array, warnings: array}
     */
    public function bulkCreate(User $user, Schedule $schedule, array $shifts): array
    {
        ScheduleBoardAccessHelper::assertCanMutate($user, $schedule);

        $store = $this->storeContext->requireForUser($user);
        $weekStartDate = $schedule->week_start_date->toDateString();
        $context = $this->validation->makeContext($store, $weekStartDate);

        $toCreate = [];
        $blocks = [];

        foreach ($shifts as $index => $row) {
            $employee = $this->employeeRepository->findInStore($store->id, (int) $row['employee_id']);
            if ($employee === null) {
                $blocks["shifts.{$index}.employee_id"] = ['Employee does not belong to this store.'];
                continue;
            }
            ScheduleBoardAccessHelper::assertCanTargetEmployee($user, $employee);

            $candidate = $this->candidate($store->id, $schedule->id, $row);
            $rowBlocks = $this->validation->blocks($candidate, $context, "shifts.{$index}.");

            if ($rowBlocks !== []) {
                $blocks = [...$blocks, ...$rowBlocks];
                continue;
            }

            $toCreate[] = [
                ...$candidate,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->validation->assertNotBlocked($blocks, 'One or more selected cells break a rule.');

        $created = DB::transaction(function () use ($toCreate): int {
            return $this->shiftRepository->insertMany($toCreate);
        });

        UserActivityHelper::log(
            'schedules',
            'bulk_tag',
            "Tagged {$created} shift(s) on week {$weekStartDate}.",
            $schedule->id,
        );

        $freshContext = $this->validation->makeContext($store, $weekStartDate);
        $shiftRows = ScheduleBoardAccessHelper::crewShifts(
            $user,
            $this->shiftRepository->getForSchedule($schedule->id),
        );

        return [
            'count' => $created,
            'shifts' => $shiftRows->map(static fn (ScheduleShift $shift) => ScheduleShiftMapper::map($shift))->all(),
            'warnings' => $this->validation->weekWarnings($freshContext),
        ];
    }

    private function mappedWithWarnings(Store $store, Schedule $schedule, ScheduleShift $shift): array
    {
        $context = $this->validation->makeContext($store, $schedule->week_start_date->toDateString());
        $mapped = ScheduleShiftMapper::map($shift);

        return [
            'shift' => $mapped,
            'warnings' => $this->validation->shiftWarnings($mapped, $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function candidate(int $storeId, int $scheduleId, array $data): array
    {
        $data = $this->applyTemplateTimes($storeId, $data);

        return [
            'store_id' => $storeId,
            'schedule_id' => $scheduleId,
            'employee_id' => (int) $data['employee_id'],
            'shift_date' => $data['shift_date'],
            'shift_template_id' => $data['shift_template_id'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'break_minutes' => $data['break_minutes'] ?? 0,
            'crew_station_id' => $data['crew_station_id'] ?? null,
            'manager_position_id' => $data['manager_position_id'] ?? null,
            'is_rest_day' => filter_var($data['is_rest_day'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyTemplateTimes(int $storeId, array $data): array
    {
        if (!empty($data['is_rest_day'])) {
            $data['start_time'] = null;
            $data['end_time'] = null;
            $data['break_minutes'] = 0;

            return $data;
        }

        $templateId = $data['shift_template_id'] ?? null;
        if ($templateId === null || $templateId === '') {
            return $data;
        }

        $template = $this->templateRepository->findInStore($storeId, (int) $templateId);
        if ($template === null) {
            return $data;
        }

        $data['start_time'] = substr((string) $template->start_time, 0, 5);
        $data['end_time'] = substr((string) $template->end_time, 0, 5);
        $data['break_minutes'] = $template->break_minutes;

        return $data;
    }

    private function assertShiftOnSchedule(Store $store, Schedule $schedule, ScheduleShift $shift): void
    {
        if ((int) $shift->schedule_id !== (int) $schedule->id || (int) $shift->store_id !== (int) $store->id) {
            abort(400, 'Shift does not belong to this schedule.');
        }
    }
}
