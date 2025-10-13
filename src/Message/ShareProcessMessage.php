<?php

namespace App\Message;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_doctrine')]
readonly class ShareProcessMessage
{
    /**
     * @param  string  $processId
     * @param  EncryptedClientDataDto  $encryptedClientData
     * @param  string|null  $clientIpAddress
     * @param  string|null  $userAgent
     */
    public function __construct(
        public string $processId,
        public EncryptedClientDataDto $encryptedClientData,
        public ?string $clientIpAddress = null,
        public ?string $userAgent = null
    ) {
    }
}
