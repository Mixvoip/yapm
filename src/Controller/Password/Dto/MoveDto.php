<?php

/**
 * @author bsteffan
 * @since 2025-10-28
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\AuthenticationDataDto;
use Symfony\Component\Validator\Constraints as Assert;

class MoveDto
{
    /**
     * @param  AuthenticationDataDto  $authData
     * @param  string  $vaultId
     * @param  string|null  $folderId
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Valid
        ]
        public AuthenticationDataDto $authData,

        #[Assert\NotBlank]
        public string $vaultId,

        public ?string $folderId = null
    ) {
    }
}
