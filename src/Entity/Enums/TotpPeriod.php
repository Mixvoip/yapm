<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Entity\Enums;

enum TotpPeriod: int
{
    case Thirty = 30;
    case Sixty = 60;
}
