<?php

/**
 * @author bsteffan
 * @since 2025-06-20
 */

namespace App\Entity\Enums;

enum PasswordField: string
{
    case ExternalId = "external_id";
    case Target = "target";
    case Location = "location";
}
