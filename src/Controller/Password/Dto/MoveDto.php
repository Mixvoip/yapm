<?php

/**
 * @author bsteffan
 * @since 2025-10-28
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

class MoveDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedUserPassword
     * @param  string  $vaultId
     * @param  string|null  $folderId
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Valid
        ]
        public EncryptedClientDataDto $encryptedUserPassword,

        #[Assert\NotBlank]
        public string $vaultId,

        public ?string $folderId = null
    ) {
    }
}
