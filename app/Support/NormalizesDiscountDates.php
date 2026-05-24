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

    protected static function normalizeDiscountDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay()->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp)->startOfDay()->format('Y-m-d H:i:s');
    }
}
