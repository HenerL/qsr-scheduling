<?php

namespace Tests\Unit;

use App\Helpers\DateHelper;
use Tests\TestCase;

class DateHelperTest extends TestCase
{
    public function test_month_key_and_bounds(): void
    {
        $this->assertSame('2026-08', DateHelper::monthKey('2026-08-31'));
        $this->assertSame('2026-08-01', DateHelper::monthStartDate('2026-08'));
        $this->assertSame('2026-08-31', DateHelper::monthEndDate('2026-08'));
        $this->assertSame('2026-02-28', DateHelper::monthEndDate('2026-02'));
        $this->assertCount(31, DateHelper::monthDates('2026-08'));
        $this->assertSame('2026-08-01', DateHelper::monthDates('2026-08')[0]);
        $this->assertSame('2026-08-31', DateHelper::monthDates('2026-08')[30]);
    }

    public function test_month_meta_shape(): void
    {
        $this->assertSame(
            [
                'key' => '2026-08',
                'year' => 2026,
                'month' => 8,
                'title' => 'August',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ],
            DateHelper::monthMeta('2026-08'),
        );
    }
}
