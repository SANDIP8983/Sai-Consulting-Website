<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Holiday;
use App\Models\OfficeTiming;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AppointmentAvailabilityService
{
    public const TIMEZONE = 'Asia/Kolkata';

    public const SLOT_MINUTES = 30;

    public function slots(string $date, ?int $excludingAppointmentId = null): array
    {
        $day = CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();
        if ($day->lt(now(self::TIMEZONE)->startOfDay()) || $day->gt(now(self::TIMEZONE)->addMonths(6)->endOfDay()) || $this->closedHoliday($day)) {
            return [];
        }
        $timing = OfficeTiming::query()->where('day_of_week', $day->dayOfWeek)->first();
        if (! $timing || $timing->is_closed || ! $timing->opens_at || ! $timing->closes_at) {
            return [];
        }
        $start = $day->setTimeFromTimeString($timing->opens_at);
        $close = $day->setTimeFromTimeString($timing->closes_at);
        $busy = Appointment::query()->whereIn('status', AppointmentStatus::active())->when($excludingAppointmentId, fn ($q) => $q->whereKeyNot($excludingAppointmentId))->whereBetween('scheduled_at', [$day, $day->endOfDay()])->get();
        $blocks = AppointmentBlock::query()->whereDate('block_date', $day->toDateString())->get();
        $slots = [];
        for ($at = $start; $at->addMinutes(self::SLOT_MINUTES)->lte($close); $at = $at->addMinutes(self::SLOT_MINUTES)) {
            $end = $at->addMinutes(self::SLOT_MINUTES);
            if ($at->lte(now(self::TIMEZONE)) || $this->overlapsAppointments($at, $end, $busy) || $this->overlapsBlocks($at, $end, $blocks)) {
                continue;
            }
            $slots[] = ['value' => $at->format('H:i'), 'label' => $at->format('g:i A').' – '.$end->format('g:i A')];
        }

        return $slots;
    }

    public function scheduledAt(string $date, string $time, ?int $excludingAppointmentId = null): CarbonImmutable
    {
        $at = CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $time", self::TIMEZONE);
        if (! collect($this->slots($date, $excludingAppointmentId))->contains('value', $at->format('H:i'))) {
            abort(422, 'The selected appointment slot is no longer available.');
        }

        return $at;
    }

    private function closedHoliday(CarbonImmutable $day): bool
    {
        return Holiday::query()->where('is_closed', true)->get()->contains(fn (Holiday $h) => $h->is_recurring ? $h->holiday_date->format('m-d') === $day->format('m-d') : $h->holiday_date->isSameDay($day));
    }

    private function overlapsAppointments($start, $end, Collection $items): bool
    {
        return $items->contains(fn (Appointment $a) => $start->lt($a->scheduled_at->addMinutes($a->duration_minutes)) && $end->gt($a->scheduled_at));
    }

    private function overlapsBlocks($start, $end, Collection $items): bool
    {
        return $items->contains(function (AppointmentBlock $b) use ($start, $end) {
            if ($b->full_day) {
                return true;
            } $bs = $start->startOfDay()->setTimeFromTimeString($b->starts_at);
            $be = $start->startOfDay()->setTimeFromTimeString($b->ends_at);

            return $start->lt($be) && $end->gt($bs);
        });
    }
}
