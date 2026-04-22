<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Entity\Enums;

use DateInterval;

enum TimeBasedShareDuration: string
{
    case OneHour = '1h';
    case TwoHours = '2h';
    case TwentyFourHours = '24h';

    /**
     * @return DateInterval
     */
    public function toDateInterval(): DateInterval
    {
        return match ($this) {
            self::OneHour => new DateInterval('PT1H'),
            self::TwoHours => new DateInterval('PT2H'),
            self::TwentyFourHours => new DateInterval('PT24H'),
        };
    }
}
