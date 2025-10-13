<?php

/**
 * @author bsteffan
 * @since 2025-09-24
 */

namespace App\Message;

use App\Service\CleanUp\PartialAccessCleaner;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class PartialAccessCleanUpMessageHandler
{
    /**
     * @param  PartialAccessCleaner  $partialAccessCleaner
     */
    public function __construct(private PartialAccessCleaner $partialAccessCleaner)
    {
    }

    /**
     * @param  PartialAccessCleanUpMessage  $message
     *
     * @return void
     * @throws Exception
     */
    public function __invoke(PartialAccessCleanUpMessage $message): void
    {
        $this->partialAccessCleaner->cleanUp($message->vaultId, $message->groupIds);
    }
}
