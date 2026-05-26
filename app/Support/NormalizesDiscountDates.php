<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

trait NormalizesDiscountDates
{
    public function setStartAtAttribute($value): void
    {
        $this->attributes['start_at'] = static::normalizeDiscountDate($value);
    }

    public function setEndAtAttribute($value): void
    {
        $this->attributes['end_at'] = static::normalizeDiscountDate($value);
    }

    public static function normalizeDiscountDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timezone = config('app.timezone', 'Europe/Vilnius');

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)
                ->timezone($timezone)
                ->startOfDay()
                ->format('Y-m-d');
        }

        $value = trim((string) $value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match)) {
            return Carbon::parse($match[1], $timezone)
                ->startOfDay()
                ->format('Y-m-d');
        }

        try {
            return Carbon::parse($value, $timezone)
                ->startOfDay()
                ->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
