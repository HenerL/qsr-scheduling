<?php

namespace Tests\Unit\CoreFunctions;

use App\Helpers\TimeHelper;
use App\Services\Shared\CoreFunctions\RestBetweenShiftsCoreFunction;
use App\Services\Shared\CoreFunctions\RestDayConflictCoreFunction;
use App\Services\Shared\CoreFunctions\ShiftOverlapCoreFunction;
use App\Services\Shared\CoreFunctions\ShiftWindowCoreFunction;
use App\Services\Shared\CoreFunctions\ShiftWithinOperatingHoursCoreFunction;
use App\Services\Shared\CoreFunctions\StationCoverageCoreFunction;
use App\Services\Shared\CoreFunctions\StationEligibilityCoreFunction;
use App\Services\Shared\CoreFunctions\WeeklyHoursCoreFunction;
use PHPUnit\Framework\TestCase;

class ShiftRulesTest extends TestCase
{
    private function shift(array $overrides = []): array
    {
        return [
            'id' => null,
            'employee_id' => 1,
            'shift_date' => '2026-08-24',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_minutes' => 60,
            'crew_station_id' => null,
            'is_rest_day' => false,
            ...$overrides,
        ];
    }

    private function hours(array $overrides = []): array
    {
        return [
            'is_open' => true,
            'is_24_hours' => false,
            'open_time' => '08:00',
            'close_time' => '22:00',
            ...$overrides,
        ];
    }

    // --- overnight math ---

    public function test_overnight_shift_spans_into_the_next_day(): void
    {
        $this->assertSame(480, TimeHelper::durationMinutes('22:00', '06:00'));
        $this->assertSame(7.5, TimeHelper::netHours('22:00', '06:00', 30));

        $window = ShiftWindowCoreFunction::absolute(
            $this->shift(['start_time' => '22:00', 'end_time' => '06:00']),
            '2026-08-24',
        );

        $this->assertSame([1320, 1800], $window);
    }

    public function test_rest_day_has_no_window(): void
    {
        $this->assertNull(ShiftWindowCoreFunction::absolute(
            $this->shift(['is_rest_day' => true, 'start_time' => null, 'end_time' => null]),
            '2026-08-24',
        ));
    }

    // --- operating hours (block) ---

    public function test_shift_ending_exactly_at_closing_time_is_allowed(): void
    {
        $shift = $this->shift(['start_time' => '14:00', 'end_time' => '22:00']);

        $this->assertNull(ShiftWithinOperatingHoursCoreFunction::check($shift, $this->hours()));
    }

    public function test_shift_starting_before_opening_is_blocked(): void
    {
        $shift = $this->shift(['start_time' => '06:00', 'end_time' => '14:00']);

        $this->assertSame(
            'Shift must fall inside store hours 08:00–22:00.',
            ShiftWithinOperatingHoursCoreFunction::check($shift, $this->hours()),
        );
    }

    public function test_twenty_four_hour_store_accepts_any_window(): void
    {
        $shift = $this->shift(['start_time' => '22:00', 'end_time' => '06:00']);

        $this->assertNull(ShiftWithinOperatingHoursCoreFunction::check(
            $shift,
            $this->hours(['is_24_hours' => true, 'open_time' => null, 'close_time' => null]),
        ));
    }

    public function test_store_closing_after_midnight_accepts_a_late_shift(): void
    {
        $shift = $this->shift(['start_time' => '18:00', 'end_time' => '02:00']);

        $this->assertNull(ShiftWithinOperatingHoursCoreFunction::check(
            $shift,
            $this->hours(['open_time' => '10:00', 'close_time' => '02:00']),
        ));
    }

    public function test_closed_day_is_blocked(): void
    {
        $this->assertSame(
            'The store is closed on this date.',
            ShiftWithinOperatingHoursCoreFunction::check($this->shift(), $this->hours(['is_open' => false])),
        );
    }

    public function test_rest_day_ignores_operating_hours(): void
    {
        $shift = $this->shift(['is_rest_day' => true, 'start_time' => null, 'end_time' => null]);

        $this->assertNull(ShiftWithinOperatingHoursCoreFunction::check($shift, $this->hours(['is_open' => false])));
    }

    // --- overlap (block) ---

    public function test_back_to_back_shifts_touching_at_the_boundary_do_not_overlap(): void
    {
        $existing = [$this->shift(['id' => 10, 'start_time' => '14:00', 'end_time' => '22:00'])];
        $candidate = $this->shift(['start_time' => '22:00', 'end_time' => '06:00']);

        $this->assertNull(ShiftOverlapCoreFunction::firstOverlap($candidate, $existing));
    }

    public function test_overlapping_shifts_are_detected(): void
    {
        $existing = [$this->shift(['id' => 10, 'start_time' => '10:00', 'end_time' => '18:00'])];
        $candidate = $this->shift(['start_time' => '14:00', 'end_time' => '22:00']);

        $this->assertSame(10, ShiftOverlapCoreFunction::firstOverlap($candidate, $existing)['id']);
    }

    public function test_overnight_shift_overlaps_the_next_mornings_shift(): void
    {
        $existing = [$this->shift([
            'id' => 11,
            'shift_date' => '2026-08-25',
            'start_time' => '05:00',
            'end_time' => '13:00',
        ])];
        $candidate = $this->shift(['start_time' => '22:00', 'end_time' => '06:00']);

        $this->assertSame(11, ShiftOverlapCoreFunction::firstOverlap($candidate, $existing)['id']);
    }

    public function test_editing_a_shift_does_not_overlap_itself(): void
    {
        $existing = [$this->shift(['id' => 12, 'start_time' => '06:00', 'end_time' => '14:00'])];
        $candidate = $this->shift(['id' => 12, 'start_time' => '07:00', 'end_time' => '15:00']);

        $this->assertNull(ShiftOverlapCoreFunction::firstOverlap($candidate, $existing));
    }

    // --- rest day conflict (block) ---

    public function test_rest_day_cannot_be_added_when_a_shift_exists(): void
    {
        $existing = [$this->shift(['id' => 20])];
        $candidate = $this->shift(['is_rest_day' => true, 'start_time' => null, 'end_time' => null]);

        $this->assertSame(
            'This date already has a shift, so it cannot be a rest day.',
            RestDayConflictCoreFunction::check($candidate, $existing),
        );
    }

    public function test_shift_cannot_be_added_on_a_rest_day(): void
    {
        $existing = [$this->shift(['id' => 21, 'is_rest_day' => true, 'start_time' => null, 'end_time' => null])];

        $this->assertSame(
            'This date is marked as a rest day.',
            RestDayConflictCoreFunction::check($this->shift(), $existing),
        );
    }

    public function test_rest_day_on_another_date_is_fine(): void
    {
        $existing = [$this->shift([
            'id' => 22,
            'shift_date' => '2026-08-25',
            'is_rest_day' => true,
            'start_time' => null,
            'end_time' => null,
        ])];

        $this->assertNull(RestDayConflictCoreFunction::check($this->shift(), $existing));
    }

    // --- station eligibility (warn) ---

    public function test_untrained_station_warns(): void
    {
        $shift = $this->shift(['crew_station_id' => 5]);

        $this->assertSame(
            'Not trained on the assigned station.',
            StationEligibilityCoreFunction::check($shift, [1, 2]),
        );
        $this->assertNull(StationEligibilityCoreFunction::check($shift, [1, 5]));
    }

    public function test_shift_without_a_station_never_warns(): void
    {
        $this->assertNull(StationEligibilityCoreFunction::check($this->shift(), []));
    }

    // --- weekly hours (warn) ---

    public function test_weekly_hours_warn_only_past_the_limit(): void
    {
        $this->assertNull(WeeklyHoursCoreFunction::check(40, 40));
        $this->assertNull(WeeklyHoursCoreFunction::check(48, null));
        $this->assertSame(
            'Weekly hours 42.5h exceed the 40h limit.',
            WeeklyHoursCoreFunction::check(42.5, 40),
        );
    }

    public function test_week_boundary_shift_counts_once_in_the_week_it_starts(): void
    {
        // Saturday 22:00 → Sunday 06:00 is 7.5 paid hours, all credited to Saturday.
        $saturdayNight = TimeHelper::netHours('22:00', '06:00', 30);
        $weekHours = (5 * 7) + $saturdayNight;

        $this->assertSame(42.5, $weekHours);
        $this->assertSame(
            'Weekly hours 42.5h exceed the 40h limit.',
            WeeklyHoursCoreFunction::check($weekHours, 40),
        );
    }

    // --- rest between shifts (warn) ---

    public function test_short_turnaround_warns(): void
    {
        // Closes at 22:00, opens again at 06:00 the next morning — 8h exactly, no warning.
        $closing = $this->shift(['id' => 30, 'start_time' => '14:00', 'end_time' => '22:00']);
        $opening = $this->shift(['id' => 31, 'shift_date' => '2026-08-25', 'start_time' => '06:00', 'end_time' => '14:00']);

        $this->assertNull(RestBetweenShiftsCoreFunction::check($opening, [$closing]));

        $earlyOpening = $this->shift(['id' => 32, 'shift_date' => '2026-08-25', 'start_time' => '05:00', 'end_time' => '13:00']);

        $this->assertSame(
            'Only 7h 00m rest before or after the next shift (8h recommended).',
            RestBetweenShiftsCoreFunction::check($earlyOpening, [$closing]),
        );
    }

    public function test_overlapping_shifts_are_left_to_the_overlap_block(): void
    {
        $existing = [$this->shift(['id' => 33, 'start_time' => '10:00', 'end_time' => '18:00'])];
        $candidate = $this->shift(['id' => 34, 'start_time' => '14:00', 'end_time' => '22:00']);

        $this->assertNull(RestBetweenShiftsCoreFunction::check($candidate, $existing));
    }

    // --- station coverage (warn) ---

    public function test_coverage_gap_reported_for_open_days_only(): void
    {
        $stations = [
            ['station_id' => 1, 'station_name' => 'Grill', 'min_crew_required' => 2],
            ['station_id' => 2, 'station_name' => 'Beverage', 'min_crew_required' => null],
        ];
        $counts = ['2026-08-24' => [1 => 1]];

        $gaps = StationCoverageCoreFunction::gaps($counts, $stations, ['2026-08-24']);

        $this->assertCount(1, $gaps);
        $this->assertSame('Grill has 1 of 2 required crew.', $gaps[0]['message']);

        $this->assertSame([], StationCoverageCoreFunction::gaps($counts, $stations, []));
    }

    public function test_fully_staffed_station_reports_no_gap(): void
    {
        $stations = [['station_id' => 1, 'station_name' => 'Grill', 'min_crew_required' => 2]];

        $this->assertSame(
            [],
            StationCoverageCoreFunction::gaps(['2026-08-24' => [1 => 2]], $stations, ['2026-08-24']),
        );
    }
}
