<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class WorkdayCalendar
{
    public function weekdaysInRange(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()){
            // isoWeekday: 1=Mon ... 5=Fri
            if ($d->isoWeekday() <= 5) {
                $dates[] = $d;
            }
        }

        return $dates;
    }

    public function nextWeekday(string $date): CarbonImmutable
    {
        $d = CarbonImmutable::parse($date)->startOfDay()->addDay();
        while ($d->isoWeekday() > 5) {
            $d = $d->addDay();
        }
        return $d;
    }
}