<?php

/**
 * @author bsteffan
 * @since 2025-08-26
 */

namespace App\Entity\Enums\ShareItem;

enum Status: string
{
    case Pending = "pending";
    case Done = "done";
    case Failed = "failed";
}
