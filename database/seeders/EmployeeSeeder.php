<?php

namespace Database\Seeders;

use App\Helpers\MasterfileRecordIdHelper;
use App\Models\Employee;
use App\Models\ManagerPosition;
use App\Models\CrewStation;
use App\Models\User;
use App\Models\EmployeeStation;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the employee test seeder.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Ensure a store exists – use the first one or create a new test store.
            // Ensure a test user exists to own the store
            $owner = User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => bcrypt('password'),
                    'user_type' => 'manager',
                    'is_active' => true,
                    'store_id' => null,
                ]
            );

            // Find an existing test store or create one (idempotent)
            $store = Store::firstOrCreate(
                ['store_code' => 'test-store'],
                [
                    'owner_user_id' => $owner->id,
                    'store_name' => 'Test Store',
                    'branch_name' => null,
                    'address' => '123 Test St',
                    'contact_number' => '555-0100',
                    'timezone' => 'Asia/Manila',
                    'week_starts_on' => 0,
                    'max_consecutive_duty_days' => 6,
                    'onboarding_step' => 7,
                    'onboarding_completed_at' => now(),
                    'is_active' => true,
                ]
            );

            // Create manager positions only if they don't already exist for this store
            $managerPositions = [];
            $positions = ['Store Manager', 'Assistant Manager'];
            foreach ($positions as $idx => $name) {
                $managerPositions[] = ManagerPosition::firstOrCreate(
                    ['store_id' => $store->id, 'position_name' => $name],
                    [
                        'position_description' => "$name for {$store->store_name}",
                        'sort_order' => $idx + 1,
                        'is_active' => true,
                    ]
                );
            }

            // Create crew stations only if they don't already exist for this store
            $crewStations = [];
            $stations = ['Front Counter', 'Grill'];
            foreach ($stations as $idx => $name) {
                $crewStations[] = CrewStation::firstOrCreate(
                    ['store_id' => $store->id, 'station_name' => $name],
                    [
                        'station_description' => "$name station",
                        'min_crew_required' => 1,
                        'sort_order' => $idx + 1,
                        'is_active' => true,
                    ]
                );
            }

                    // Create a manager employee only if one with the same employee_no does not exist
            $manager = Employee::firstOrCreate(
                [
                    'store_id' => $store->id,
                    'employee_no' => MasterfileRecordIdHelper::next('employees', 'employee_no', 'EMP', $store->id),
                ],
                [
                    'first_name' => 'Alice',
                    'last_name' => 'Manager',
                    'middle_name' => null,
                    'employee_type' => Employee::TYPE_MANAGER,
                    'manager_position_id' => $managerPositions[0]->position_id,
                    'primary_station_id' => null,
                    'employment_status' => 'full_time',
                    'date_hired' => now()->subYears(2)->toDateString(),
                    'contact_number' => '555-0001',
                    'max_hours_per_week' => 40,
                    'user_id' => null,
                    'is_active' => true,
                ]
            );

                    // Create a crew employee only if one with the same employee_no does not exist
            $crew = Employee::firstOrCreate(
                [
                    'store_id' => $store->id,
                    'employee_no' => MasterfileRecordIdHelper::next('employees', 'employee_no', 'EMP', $store->id),
                ],
                [
                    'first_name' => 'Bob',
                    'last_name' => 'Crew',
                    'middle_name' => null,
                    'employee_type' => Employee::TYPE_CREW,
                    'manager_position_id' => null,
                    'primary_station_id' => $crewStations[0]->station_id,
                    'employment_status' => 'part_time',
                    'date_hired' => now()->subMonths(6)->toDateString(),
                    'contact_number' => '555-0002',
                    'max_hours_per_week' => 24,
                    'user_id' => null,
                    'is_active' => true,
                ]
            );

                    // Attach a secondary station to the crew employee only if it doesn't already exist
            EmployeeStation::firstOrCreate([
                'employee_id' => $crew->id,
                'station_id' => $crewStations[1]->station_id,
                'proficiency' => 'certified',
            ]);
        });
    }
}
