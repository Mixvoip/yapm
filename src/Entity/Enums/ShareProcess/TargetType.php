<?php

/**
 * @author bsteffan
 * @since 2025-08-26
 */

namespace App\Entity\Enums\ShareProcess;

enum TargetType: string
{
    case Folder = "folder";
    case Vault = "vault";
}
