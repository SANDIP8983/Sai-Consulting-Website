<?php

namespace App\Casts;

use App\Services\AppointmentAvailabilityService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AppointmentDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value, AppointmentAvailabilityService::TIMEZONE);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)->setTimezone(AppointmentAvailabilityService::TIMEZONE)
            : CarbonImmutable::parse($value, AppointmentAvailabilityService::TIMEZONE);

        return $date->format('Y-m-d H:i:s');
    }
}
