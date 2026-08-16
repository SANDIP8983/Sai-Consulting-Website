<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\OfficeTiming;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BusinessCalendarService
{
    public const TIMEZONE = 'Asia/Kolkata';

    public function isWorkingDay(CarbonInterface|string $date): bool
    {
        $day = $this->date($date);

        if ($this->isAutomaticClosedDay($day) || $this->configuredHoliday($day)) {
            return false;
        }

        $timing = OfficeTiming::query()->where('day_of_week', $day->dayOfWeek)->first();

        if ($timing === null) {
            return ! OfficeTiming::query()->exists();
        }

        return ! $timing->is_closed && filled($timing->opens_at) && filled($timing->closes_at);
    }

    public function isAutomaticClosedDay(CarbonInterface|string $date): bool
    {
        $day = $this->date($date);

        return $day->isSunday()
            || ($day->isSaturday() && in_array($this->occurrenceInMonth($day), [2, 4], true));
    }

    public function closureReason(CarbonInterface|string $date): ?string
    {
        $day = $this->date($date);

        if ($day->isSunday()) {
            return 'Sunday';
        }

        if ($day->isSaturday() && in_array($this->occurrenceInMonth($day), [2, 4], true)) {
            return $this->occurrenceInMonth($day) === 2 ? 'Second Saturday' : 'Fourth Saturday';
        }

        if ($holiday = $this->configuredHoliday($day)) {
            return $holiday->title;
        }

        $timing = OfficeTiming::query()->where('day_of_week', $day->dayOfWeek)->first();

        if (! $timing && ! OfficeTiming::query()->exists()) {
            return null;
        }

        return ! $timing || $timing->is_closed || ! $timing->opens_at || ! $timing->closes_at
            ? 'Office timing is closed'
            : null;
    }

    public function addWorkingDays(CarbonInterface|string $date, int $days): CarbonImmutable
    {
        $result = $this->date($date);

        while ($days > 0) {
            $result = $result->addDay();
            if ($this->isWorkingDay($result)) {
                $days--;
            }
        }

        return $result;
    }

    private function configuredHoliday(CarbonImmutable $day): ?Holiday
    {
        return Holiday::query()->where('is_closed', true)->get()->first(
            fn (Holiday $holiday): bool => $holiday->is_recurring
                ? $holiday->holiday_date->format('m-d') === $day->format('m-d')
                : $holiday->holiday_date->isSameDay($day),
        );
    }

    private function occurrenceInMonth(CarbonImmutable $day): int
    {
        return intdiv($day->day - 1, 7) + 1;
    }

    private function date(CarbonInterface|string $date): CarbonImmutable
    {
        return $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE)->startOfDay()
            : CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();
    }
}
