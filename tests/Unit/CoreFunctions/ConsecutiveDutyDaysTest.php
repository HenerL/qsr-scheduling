<?php

namespace Tests\Unit\CoreFunctions;

use App\Services\Shared\CoreFunctions\ConsecutiveDutyDaysCoreFunction;
use PHPUnit\Framework\TestCase;

/**
 * PLAN §9 calls this the rule most likely to be quietly wrong, so each break in a run
 * gets its own case. Rest days, cancelled shifts and untagged days are all simply
 * absent from the duty-date list the repository hands over.
 */
class ConsecutiveDutyDaysTest extends TestCase
{
    public function test_run_starting_in_the_previous_week_is_counted(): void
    {
        // Wed 19th → Sun 23rd (previous week) then Mon 24th → Tue 25th.
        $dutyDates = [
            '2026-08-19', '2026-08-20', '2026-08-21', '2026-08-22',
            '2026-08-23', '2026-08-24', '2026-08-25',
        ];

        $this->assertSame(7, ConsecutiveDutyDaysCoreFunction::longestRun($dutyDates, '2026-08-25'));
    }

    public function test_rest_day_in_the_middle_breaks_the_run(): void
    {
        // The 22nd is a rest day, so it never reaches this list.
        $dutyDates = ['2026-08-19', '2026-08-20', '2026-08-21', '2026-08-23', '2026-08-24', '2026-08-25'];

        $this->assertSame(3, ConsecutiveDutyDaysCoreFunction::longestRun($dutyDates, '2026-08-25'));
    }

    public function test_cancelled_shift_breaks_the_run(): void
    {
        // The 23rd's only shift was cancelled, so the repository excludes it.
        $dutyDates = ['2026-08-20', '2026-08-21', '2026-08-22', '2026-08-24', '2026-08-25'];

        $this->assertSame(2, ConsecutiveDutyDaysCoreFunction::longestRun($dutyDates, '2026-08-25'));
    }

    public function test_untagged_gap_day_breaks_the_run(): void
    {
        $dutyDates = ['2026-08-21', '2026-08-22', '2026-08-25'];

        $this->assertSame(1, ConsecutiveDutyDaysCoreFunction::longestRun($dutyDates, '2026-08-25'));
    }

    public function test_date_that_is_not_duty_returns_zero(): void
    {
        $this->assertSame(0, ConsecutiveDutyDaysCoreFunction::longestRun(['2026-08-24'], '2026-08-25'));
        $this->assertSame(0, ConsecutiveDutyDaysCoreFunction::longestRun([], '2026-08-25'));
    }

    public function test_run_is_counted_from_both_sides_of_the_date(): void
    {
        $dutyDates = ['2026-08-23', '2026-08-24', '2026-08-25', '2026-08-26'];

        $this->assertSame(4, ConsecutiveDutyDaysCoreFunction::longestRun($dutyDates, '2026-08-24'));
    }

    public function test_datetime_strings_are_accepted(): void
    {
        $dutyDates = ['2026-08-24 00:00:00', '2026-08-25 00:00:00'];

        $this->assertSame(2, ConsecutiveDutyDaysCoreFunction::longestRun($dutyDates, '2026-08-25'));
    }

    public function test_exactly_the_limit_does_not_warn_but_one_more_does(): void
    {
        $this->assertFalse(ConsecutiveDutyDaysCoreFunction::exceedsLimit(6, 6));
        $this->assertTrue(ConsecutiveDutyDaysCoreFunction::exceedsLimit(7, 6));
        $this->assertFalse(ConsecutiveDutyDaysCoreFunction::exceedsLimit(9, 0));
    }

    public function test_message_names_the_run_and_the_limit(): void
    {
        $this->assertSame(
            '7 consecutive duty days exceeds the 6-day limit.',
            ConsecutiveDutyDaysCoreFunction::message(7, 6),
        );
    }
}
