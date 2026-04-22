<?php

namespace App\Message;

use App\Controller\Dto\AuthenticationDataDto;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_doctrine')]
readonly class ShareProcessMessage
{
    /**
     * @param  string  $processId
     * @param  AuthenticationDataDto  $authData
     * @param  string|null  $clientIpAddress
     * @param  string|null  $userAgent
     */
    public function __construct(
        public string $processId,
        public AuthenticationDataDto $authData,
        public ?string $clientIpAddress = null,
        public ?string $userAgent = null
    ) {
    }
}
