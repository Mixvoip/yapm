<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Entity\Enums;

enum TotpDigits: int
{
    case Six = 6;
    case Eight = 8;
}
