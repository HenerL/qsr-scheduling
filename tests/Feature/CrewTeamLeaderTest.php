<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedStore;
use Tests\TestCase;

class CrewTeamLeaderTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuthenticatedStore;

    public function test_team_leader_sees_crew_rows_only_and_cannot_publish(): void
    {
        $fixture = $this->makeTeamLeaderFixture();

        $week = $this->withToken($fixture['leaderToken'])
            ->getJson('/api/schedules?week_start_date=2026-08-24');
        $week->assertOk();

        $employeeIds = collect($week->json('data.employees'))->pluck('employee_id')->all();
        $this->assertSame([$fixture['crewId']], $employeeIds);
        $this->assertCount(1, $week->json('data.shifts'));
        $this->assertSame($fixture['crewId'], $week->json('data.shifts.0.employee_id'));

        $this->withToken($fixture['leaderToken'])
            ->postJson("/api/schedules/{$fixture['sourceScheduleId']}/publish")
            ->assertUnauthorized();

        $this->withToken($fixture['leaderToken'])
            ->getJson('/api/employees')
            ->assertUnauthorized();
    }

    public function test_team_leader_can_tag_crew_but_not_manager_employees(): void
    {
        $fixture = $this->makeTeamLeaderFixture();

        $this->withToken($fixture['leaderToken'])
            ->postJson("/api/schedules/{$fixture['sourceScheduleId']}/shifts", [
                'employee_id' => $fixture['crewId'],
                'shift_date' => '2026-08-26',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_minutes' => 30,
                'crew_station_id' => $fixture['stationId'],
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $this->withToken($fixture['leaderToken'])
            ->postJson("/api/schedules/{$fixture['sourceScheduleId']}/shifts", [
                'employee_id' => $fixture['managerEmployeeId'],
                'shift_date' => '2026-08-26',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_minutes' => 30,
                'manager_position_id' => $fixture['positionId'],
                'is_rest_day' => false,
            ])
            ->assertForbidden();
    }

    public function test_team_leader_cannot_mutate_a_published_week(): void
    {
        $fixture = $this->makeTeamLeaderFixture();

        $this->withToken($fixture['managerToken'])
            ->postJson("/api/schedules/{$fixture['sourceScheduleId']}/publish")
            ->assertOk();

        $this->withToken($fixture['leaderToken'])
            ->postJson("/api/schedules/{$fixture['sourceScheduleId']}/shifts", [
                'employee_id' => $fixture['crewId'],
                'shift_date' => '2026-08-26',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_minutes' => 30,
                'crew_station_id' => $fixture['stationId'],
                'is_rest_day' => false,
            ])
            ->assertForbidden();
    }

    public function test_team_leader_copy_week_replaces_crew_shifts_and_leaves_manager_shifts(): void
    {
        $fixture = $this->makeTeamLeaderFixture();

        $targetWeek = $this->withToken($fixture['managerToken'])
            ->getJson('/api/schedules?week_start_date=2026-08-31');
        $targetWeek->assertOk();
        $targetScheduleId = $targetWeek->json('data.week.schedule_id');

        $this->withToken($fixture['managerToken'])
            ->postJson("/api/schedules/{$targetScheduleId}/shifts", [
                'employee_id' => $fixture['managerEmployeeId'],
                'shift_date' => '2026-09-01',
                'start_time' => '10:00',
                'end_time' => '18:00',
                'break_minutes' => 30,
                'manager_position_id' => $fixture['positionId'],
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $this->withToken($fixture['managerToken'])
            ->postJson("/api/schedules/{$targetScheduleId}/shifts", [
                'employee_id' => $fixture['crewId'],
                'shift_date' => '2026-09-01',
                'start_time' => '08:00',
                'end_time' => '14:00',
                'break_minutes' => 0,
                'crew_station_id' => $fixture['stationId'],
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $this->withToken($fixture['leaderToken'])
            ->postJson("/api/schedules/{$targetScheduleId}/copy-from", [
                'source_week_start_date' => '2026-08-24',
            ])
            ->assertCreated()
            ->assertJsonPath('data.copied', 1);

        $copied = $this->withToken($fixture['managerToken'])
            ->getJson('/api/schedules?week_start_date=2026-08-31');
        $copied->assertOk();

        $shifts = collect($copied->json('data.shifts'));
        $this->assertCount(2, $shifts);

        $crewShift = $shifts->firstWhere('employee_id', $fixture['crewId']);
        $this->assertNotNull($crewShift);
        $this->assertSame('2026-09-01', $crewShift['shift_date']);
        $this->assertSame('08:00', $crewShift['start_time']);
        $this->assertSame('16:00', $crewShift['end_time']);

        $managerShift = $shifts->firstWhere('employee_id', $fixture['managerEmployeeId']);
        $this->assertNotNull($managerShift);
        $this->assertSame('10:00', $managerShift['start_time']);
    }

    /**
     * @return array{
     *     managerToken: string,
     *     leaderToken: string,
     *     crewId: int,
     *     managerEmployeeId: int,
     *     stationId: int,
     *     positionId: int,
     *     sourceScheduleId: int
     * }
     */
    private function makeTeamLeaderFixture(): array
    {
        [, , $managerToken] = $this->makeManagerWithStore('lead-mgr@qrs.test', 'Leader Store');

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
        $this->assertTrue($crew->json('data.is_team_leader'));

        $managerEmployee = $this->withToken($managerToken)
            ->postJson('/api/employees', [
                'first_name' => 'Alex',
                'last_name' => 'Manager',
                'employee_type' => 'manager',
                'manager_position_id' => $positionId,
                'employment_status' => 'full_time',
                'date_hired' => '2025-06-01',
                'is_active' => true,
                'is_team_leader' => true,
            ]);
        $managerEmployee->assertCreated();
        $managerEmployeeId = $managerEmployee->json('data.id');
        $this->assertFalse($managerEmployee->json('data.is_team_leader'));

        $this->withToken($managerToken)
            ->postJson("/api/employees/{$crewId}/crew-account", [
                'email' => 'pat.lead@qrs.test',
                'password' => 'password12',
            ])
            ->assertCreated();

        $week = $this->withToken($managerToken)
            ->getJson('/api/schedules?week_start_date=2026-08-24');
        $week->assertOk();
        $sourceScheduleId = $week->json('data.week.schedule_id');

        $this->withToken($managerToken)
            ->postJson("/api/schedules/{$sourceScheduleId}/shifts", [
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
            ->postJson("/api/schedules/{$sourceScheduleId}/shifts", [
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
            'email' => 'pat.lead@qrs.test',
            'password' => 'password12',
        ]);
        $login->assertOk()
            ->assertJsonPath('data.user.user_type', 'crew')
            ->assertJsonPath('data.user.is_team_leader', true);

        return [
            'managerToken' => $managerToken,
            'leaderToken' => $login->json('data.token'),
            'crewId' => $crewId,
            'managerEmployeeId' => $managerEmployeeId,
            'stationId' => $stationId,
            'positionId' => $positionId,
            'sourceScheduleId' => $sourceScheduleId,
        ];
    }
}
