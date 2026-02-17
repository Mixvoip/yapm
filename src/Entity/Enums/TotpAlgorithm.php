<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Entity\Enums;

enum TotpAlgorithm: string
{
    case Sha1 = "sha1";
    case Sha256 = "sha256";
    case Sha512 = "sha512";
}
