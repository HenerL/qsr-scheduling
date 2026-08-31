<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedStore;
use Tests\TestCase;

class StoreIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuthenticatedStore;

    public function test_a_manager_cannot_read_another_store_position(): void
    {
        [, , $tokenA] = $this->makeManagerWithStore('a@qrs.test', 'Store A');
        [, , $tokenB] = $this->makeManagerWithStore('b@qrs.test', 'Store B');

        $created = $this->withToken($tokenA)
            ->postJson('/api/manager-positions', [
                'position_name' => 'Store Manager',
                'sort_order' => 1,
            ]);

        $created->assertCreated();
        $positionId = $created->json('data.position_id');

        $this->withToken($tokenB)
            ->putJson("/api/manager-positions/{$positionId}", [
                'position_name' => 'Hijacked',
                'sort_order' => 1,
            ])
            ->assertNotFound();
    }

    public function test_a_manager_cannot_read_another_store_employee(): void
    {
        [, $storeA, $tokenA] = $this->makeManagerWithStore('c@qrs.test', 'Store C');
        [, , $tokenB] = $this->makeManagerWithStore('d@qrs.test', 'Store D');

        $station = $this->withToken($tokenA)
            ->postJson('/api/crew-stations', [
                'station_name' => 'Grill',
                'min_crew_required' => 1,
                'sort_order' => 1,
            ]);
        $station->assertCreated();

        $employee = $this->withToken($tokenA)
            ->postJson('/api/employees', [
                'first_name' => 'Ana',
                'last_name' => 'Cruz',
                'employee_type' => 'crew',
                'primary_station_id' => $station->json('data.station_id'),
                'employment_status' => 'full_time',
                'date_hired' => '2026-01-15',
                'is_active' => true,
            ]);
        $employee->assertCreated();

        $this->withToken($tokenB)
            ->putJson('/api/employees/' . $employee->json('data.id'), [
                'first_name' => 'Hijacked',
                'last_name' => 'Crew',
                'employee_type' => 'crew',
                'primary_station_id' => $station->json('data.station_id'),
                'employment_status' => 'full_time',
                'date_hired' => '2026-01-15',
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertNotNull($storeA);
    }
}
