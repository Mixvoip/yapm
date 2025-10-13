<?php

/**
 * @author bsteffan
 * @since 2025-10-07
 */

namespace App\Message;

use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_doctrine')]
readonly class ProcessDeletionAuditMessage
{
    /**
     * @param  string  $entityType
     * @param  string  $entityId
     * @param  string  $deletedBy
     * @param  DateTimeImmutable  $deletedAt
     * @param  string|null  $clientIpAddress
     * @param  string|null  $userAgent
     */
    public function __construct(
        public string $entityType,  // 'vault' or 'folder'
        public string $entityId,
        public string $deletedBy,
        public DateTimeImmutable $deletedAt,
        public ?string $clientIpAddress = null,
        public ?string $userAgent = null
    ) {
    }
}
