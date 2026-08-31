<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedStore;
use Tests\TestCase;

class CrewAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuthenticatedStore;

    public function test_manager_can_provision_a_crew_login_and_crew_sees_only_published_shifts(): void
    {
        [, , $managerToken] = $this->makeManagerWithStore('mgr@qrs.test', 'Pearl Store');

        $station = $this->withToken($managerToken)
            ->postJson('/api/crew-stations', [
                'station_name' => 'Front Counter',
                'min_crew_required' => 1,
                'sort_order' => 1,
            ]);
        $station->assertCreated();

        $employee = $this->withToken($managerToken)
            ->postJson('/api/employees', [
                'first_name' => 'Bob',
                'last_name' => 'Crew',
                'employee_type' => 'crew',
                'primary_station_id' => $station->json('data.station_id'),
                'employment_status' => 'part_time',
                'date_hired' => '2026-03-01',
                'is_active' => true,
            ]);
        $employee->assertCreated();
        $employeeId = $employee->json('data.id');

        $this->withToken($managerToken)
            ->postJson("/api/employees/{$employeeId}/crew-account", [
                'email' => 'bob@qrs.test',
                'password' => 'password12',
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_login', true);

        $week = $this->withToken($managerToken)
            ->getJson('/api/schedules?week_start_date=2026-08-24');
        $week->assertOk();
        $scheduleId = $week->json('data.week.schedule_id');

        $this->withToken($managerToken)
            ->postJson("/api/schedules/{$scheduleId}/shifts", [
                'employee_id' => $employeeId,
                'shift_date' => '2026-08-25',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 30,
                'crew_station_id' => $station->json('data.station_id'),
                'is_rest_day' => false,
            ])
            ->assertCreated();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $crewLogin = $this->postJson('/api/auth/login', [
            'email' => 'bob@qrs.test',
            'password' => 'password12',
        ]);
        $crewLogin->assertOk();
        $crewLogin->assertJsonPath('data.user.is_team_leader', false);
        $crewToken = $crewLogin->json('data.token');

        $this->withToken($crewToken)
            ->getJson('/api/schedules?week_start_date=2026-08-24')
            ->assertUnauthorized();

        $draftView = $this->withToken($crewToken)
            ->getJson('/api/crew/my-schedule?week_start_date=2026-08-24');
        $draftView->assertOk();
        $this->assertFalse($draftView->json('data.week.is_published'));
        $this->assertSame([], $draftView->json('data.shifts'));

        $this->withToken($managerToken)
            ->postJson("/api/schedules/{$scheduleId}/publish")
            ->assertOk();

        $publishedView = $this->withToken($crewToken)
            ->getJson('/api/crew/my-schedule?week_start_date=2026-08-24');
        $publishedView->assertOk();
        $this->assertTrue($publishedView->json('data.week.is_published'));
        $this->assertCount(1, $publishedView->json('data.shifts'));
    }

    public function test_second_crew_account_on_the_same_employee_is_rejected(): void
    {
        [, , $managerToken] = $this->makeManagerWithStore('mgr2@qrs.test', 'Second Store');

        $station = $this->withToken($managerToken)
            ->postJson('/api/crew-stations', [
                'station_name' => 'Grill',
                'min_crew_required' => 1,
                'sort_order' => 1,
            ]);
        $employee = $this->withToken($managerToken)
            ->postJson('/api/employees', [
                'first_name' => 'Cara',
                'last_name' => 'Crew',
                'employee_type' => 'crew',
                'primary_station_id' => $station->json('data.station_id'),
                'employment_status' => 'full_time',
                'date_hired' => '2026-03-01',
                'is_active' => true,
            ]);

        $this->withToken($managerToken)
            ->postJson('/api/employees/' . $employee->json('data.id') . '/crew-account', [
                'email' => 'cara@qrs.test',
                'password' => 'password12',
            ])
            ->assertCreated();

        $this->withToken($managerToken)
            ->postJson('/api/employees/' . $employee->json('data.id') . '/crew-account', [
                'email' => 'cara2@qrs.test',
                'password' => 'password12',
            ])
            ->assertStatus(400);
    }
}
