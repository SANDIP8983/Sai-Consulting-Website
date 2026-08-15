<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class IndiaDateTime
{
    public static function timezone(): string
    {
        return config('app.display_timezone', 'Asia/Kolkata');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function format(?DateTimeInterface $value, string $format = 'd M Y, g:i A'): string
    {
        return $value === null
            ? ''
            : CarbonImmutable::instance($value)->setTimezone(self::timezone())->format($format);
    }

    public static function forDateTimeLocal(?DateTimeInterface $value = null): string
    {
        return self::format($value ?? now(), 'Y-m-d\TH:i');
    }

    public static function localInputToStorage(string $value): string
    {
        return CarbonImmutable::parse($value, self::timezone())
            ->utc()
            ->format('Y-m-d H:i:s');
    }
}
