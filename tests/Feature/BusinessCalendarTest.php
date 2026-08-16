<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Services\BusinessCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    use RefreshDatabase;

    private BusinessCalendarService $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calendar = app(BusinessCalendarService::class);

        foreach (range(0, 6) as $day) {
            OfficeTiming::query()->create(['day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '18:00', 'is_closed' => false]);
        }
    }

    public function test_automatic_weekend_rules_close_only_sunday_second_and_fourth_saturday(): void
    {
        $this->assertFalse($this->calendar->isWorkingDay('2026-05-03'));
        $this->assertFalse($this->calendar->isWorkingDay('2026-05-09'));
        $this->assertFalse($this->calendar->isWorkingDay('2026-05-23'));
        $this->assertTrue($this->calendar->isWorkingDay('2026-05-02'));
        $this->assertTrue($this->calendar->isWorkingDay('2026-05-16'));
        $this->assertTrue($this->calendar->isWorkingDay('2026-05-30'));
    }

    public function test_active_configured_holiday_closes_a_normal_weekday_and_inactive_one_does_not(): void
    {
        Holiday::query()->create(['holiday_date' => '2026-05-04', 'title' => 'Government Holiday', 'is_closed' => true]);
        Holiday::query()->create(['holiday_date' => '2026-05-05', 'title' => 'Inactive Notice', 'is_closed' => false]);

        $this->assertFalse($this->calendar->isWorkingDay('2026-05-04'));
        $this->assertTrue($this->calendar->isWorkingDay('2026-05-05'));
        $this->assertTrue($this->calendar->isWorkingDay('2026-05-06'));
    }

    public function test_office_timing_closure_is_respected_on_an_otherwise_working_day(): void
    {
        OfficeTiming::query()->where('day_of_week', CarbonImmutable::parse('2026-05-06')->dayOfWeek)->update(['is_closed' => true]);

        $this->assertFalse($this->calendar->isWorkingDay('2026-05-06'));
    }

    public function test_working_day_addition_skips_automatic_weekends_and_configured_holidays(): void
    {
        Holiday::query()->create(['holiday_date' => '2026-05-11', 'title' => 'Public Holiday', 'is_closed' => true]);

        $this->assertSame('2026-05-12', $this->calendar->addWorkingDays('2026-05-08', 1)->toDateString());
        $this->assertSame('2026-05-28', $this->calendar->addWorkingDays('2026-05-22', 4)->toDateString());
    }
}
