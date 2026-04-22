<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Controller\TimeBasedShare\Dto;

use App\Controller\Dto\AuthenticationDataDto;
use App\Entity\Enums\TimeBasedShareDuration;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateTimeBasedShareDto
{
    /**
     * @param  string  $passwordId
     * @param  string  $recipientId
     * @param  TimeBasedShareDuration  $duration
     * @param  AuthenticationDataDto  $authData
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $passwordId,

        #[Assert\NotBlank]
        public string $recipientId,

        #[Assert\NotBlank]
        public TimeBasedShareDuration $duration,

        #[
            Assert\NotBlank,
            Assert\Valid,
        ]
        public AuthenticationDataDto $authData
    ) {
    }
}
