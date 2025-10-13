<?php

/**
 * @author bsteffan
 * @since 2025-07-14
 */

namespace App\Entity\Enums;

enum AuditAction: string
{
    case Created = "created";
    case Read = "read";
    case Updated = "updated";
    case Deleted = "deleted";
    case SuccessfulLogin = "successful_login";
    case FailedLogin = "failed_login";
    case RefreshedToken = "refreshed_token";
}
