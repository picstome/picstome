<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Dates of a Monday-first month grid, including the leading and trailing days
 * that complete the weeks. Shared by the agenda, the public calendar and the
 * monthly availability editor.
 */
class MonthGrid
{
    /**
     * @return array<int, CarbonImmutable>
     */
    public function days(CarbonImmutable $monthStart): array
    {
        $start = $monthStart->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
        $end = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $days = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $days[] = $date;
        }

        return $days;
    }
}
