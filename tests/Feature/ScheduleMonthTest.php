<?php

namespace Tests\Feature;

use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedStore;
use Tests\TestCase;

class ScheduleMonthTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuthenticatedStore;

    public function test_month_does_not_create_a_draft_week(): void
    {
        [, , $token] = $this->makeManagerWithStore('month-empty@qrs.test', 'Month Empty');

        $this->withToken($token)
            ->getJson('/api/schedules/month?month=2026-08')
            ->assertOk()
            ->assertJsonPath('data.month.key', '2026-08')
            ->assertJsonPath('data.month.title', 'August')
            ->assertJsonPath('data.month.start_date', '2026-08-01')
            ->assertJsonPath('data.month.end_date', '2026-08-31')
            ->assertJsonCount(31, 'data.days')
            ->assertJsonPath('data.shifts', []);

        $this->assertSame(0, Schedule::query()->count());
    }

    public function test_month_returns_tagged_shifts_and_contact_numbers(): void
    {
        [, , $token] = $this->makeManagerWithStore('month-full@qrs.test', 'Month Full');

        $position = $this->withToken($token)
            ->postJson('/api/manager-positions', [
                'position_name' => 'Shift Manager',
                'sort_order' => 1,
            ]);
        $position->assertCreated();
        $positionId = $position->json('data.position_id');

        $manager = $this->withToken($token)
            ->postJson('/api/employees', [
                'first_name' => 'Mira',
                'last_name' => 'Cruz',
                'employee_type' => 'manager',
                'manager_position_id' => $positionId,
                'employment_status' => 'full_time',
                'contact_number' => '09096663160',
                'date_hired' => '2025-01-01',
                'is_active' => true,
            ]);
        $manager->assertCreated();
        $managerId = $manager->json('data.id');

        $week = $this->withToken($token)
            ->getJson('/api/schedules?week_start_date=2026-08-24');
        $week->assertOk();
        $scheduleId = $week->json('data.week.schedule_id');

        $this->withToken($token)
            ->postJson("/api/schedules/{$scheduleId}/shifts", [
                'employee_id' => $managerId,
                'shift_date' => '2026-08-25',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 30,
                'manager_position_id' => $positionId,
                'is_rest_day' => false,
                'remarks' => 'BULK ORDER',
            ])
            ->assertCreated();

        $nextWeek = $this->withToken($token)
            ->getJson('/api/schedules?week_start_date=2026-08-31');
        $nextWeek->assertOk();
        $nextScheduleId = $nextWeek->json('data.week.schedule_id');

        $this->withToken($token)
            ->postJson("/api/schedules/{$nextScheduleId}/shifts", [
                'employee_id' => $managerId,
                'shift_date' => '2026-08-31',
                'is_rest_day' => true,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->postJson("/api/schedules/{$nextScheduleId}/shifts", [
                'employee_id' => $managerId,
                'shift_date' => '2026-09-01',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_minutes' => 0,
                'manager_position_id' => $positionId,
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $month = $this->withToken($token)
            ->getJson('/api/schedules/month?month=2026-08');
        $month->assertOk();

        $employeeIds = collect($month->json('data.employees'))->pluck('employee_id')->all();
        $this->assertContains($managerId, $employeeIds);

        $mira = collect($month->json('data.employees'))->firstWhere('employee_id', $managerId);
        $this->assertSame('09096663160', $mira['contact_number']);

        $dates = collect($month->json('data.shifts'))->pluck('shift_date')->all();
        $this->assertContains('2026-08-25', $dates);
        $this->assertContains('2026-08-31', $dates);
        $this->assertNotContains('2026-09-01', $dates);
    }

    public function test_team_leader_month_is_crew_only(): void
    {
        $fixture = $this->makeMonthTeamLeaderFixture();

        $month = $this->withToken($fixture['leaderToken'])
            ->getJson('/api/schedules/month?month=2026-08');
        $month->assertOk();

        $employeeIds = collect($month->json('data.employees'))->pluck('employee_id')->all();
        $this->assertSame([$fixture['crewId']], $employeeIds);
        $this->assertCount(1, $month->json('data.shifts'));
        $this->assertSame($fixture['crewId'], $month->json('data.shifts.0.employee_id'));
    }

    public function test_bad_month_format_is_rejected(): void
    {
        [, , $token] = $this->makeManagerWithStore('month-bad@qrs.test', 'Month Bad');

        $this->withToken($token)
            ->getJson('/api/schedules/month?month=2026-8')
            ->assertStatus(400);
    }

    /**
     * @return array{leaderToken: string, crewId: int}
     */
    private function makeMonthTeamLeaderFixture(): array
    {
        [, , $managerToken] = $this->makeManagerWithStore('month-lead-mgr@qrs.test', 'Month Leader');

        $station = $this->withToken($managerToken)
            ->postJson('/api/crew-stations', [
                'station_name' => 'Front Counter',
                'min_crew_required' => 1,
                'sort_order' => 1,
            ]);
        $station->assertCreated();
        $stationId = $station->json('data.station_id');

        $position = $this->withToken($managerToken)
            ->postJson('/api/manager-positions', [
                'position_name' => 'Shift Manager',
                'sort_order' => 1,
            ]);
        $position->assertCreated();
        $positionId = $position->json('data.position_id');

        $crew = $this->withToken($managerToken)
            ->postJson('/api/employees', [
                'first_name' => 'Pat',
                'last_name' => 'Lead',
                'employee_type' => 'crew',
                'primary_station_id' => $stationId,
                'employment_status' => 'full_time',
                'date_hired' => '2026-03-01',
                'is_active' => true,
                'is_team_leader' => true,
            ]);
        $crew->assertCreated();
        $crewId = $crew->json('data.id');

        $managerEmployee = $this->withToken($managerToken)
            ->postJson('/api/employees', [
                'first_name' => 'Alex',
                'last_name' => 'Manager',
                'employee_type' => 'manager',
                'manager_position_id' => $positionId,
                'employment_status' => 'full_time',
                'date_hired' => '2025-06-01',
                'is_active' => true,
            ]);
        $managerEmployee->assertCreated();
        $managerEmployeeId = $managerEmployee->json('data.id');

        $this->withToken($managerToken)
            ->postJson("/api/employees/{$crewId}/crew-account", [
                'email' => 'pat.month@qrs.test',
                'password' => 'password12',
            ])
            ->assertCreated();

        $week = $this->withToken($managerToken)
            ->getJson('/api/schedules?week_start_date=2026-08-24');
        $week->assertOk();
        $scheduleId = $week->json('data.week.schedule_id');

        $this->withToken($managerToken)
            ->postJson("/api/schedules/{$scheduleId}/shifts", [
                'employee_id' => $crewId,
                'shift_date' => '2026-08-25',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 30,
                'crew_station_id' => $stationId,
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $this->withToken($managerToken)
            ->postJson("/api/schedules/{$scheduleId}/shifts", [
                'employee_id' => $managerEmployeeId,
                'shift_date' => '2026-08-25',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_minutes' => 30,
                'manager_position_id' => $positionId,
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $login = $this->postJson('/api/auth/login', [
            'email' => 'pat.month@qrs.test',
            'password' => 'password12',
        ]);
        $login->assertOk();

        return [
            'leaderToken' => $login->json('data.token'),
            'crewId' => $crewId,
        ];
    }
}
