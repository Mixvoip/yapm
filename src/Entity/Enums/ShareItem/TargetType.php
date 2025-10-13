<?php

/**
 * @author bsteffan
 * @since 2025-08-26
 */

namespace App\Entity\Enums\ShareItem;

enum TargetType: string
{
    case Folder = "folder";
    case Password = "password";
}
