<?php

/**
 * @author bsteffan
 * @since 2025-09-24
 */

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_doctrine')]
readonly class PartialAccessCleanUpMessage
{
    /**
     * @param  string|null  $vaultId
     * @param  string[]  $groupIds
     */
    public function __construct(
        public ?string $vaultId = null,
        public array $groupIds = []
    ) {
    }
}
