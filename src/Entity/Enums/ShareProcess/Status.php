<?php

/**
 * @author bsteffan
 * @since 2025-08-26
 */

namespace App\Entity\Enums\ShareProcess;

enum Status: string
{
    case Pending = "pending";
    case Running = "running";
    case Completed = "completed";
    case Failed = "failed";
    case Canceled = "canceled";
}
