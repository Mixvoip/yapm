<?php

/**
 * @author bsteffan
 * @since 2025-08-06
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchSensitiveDataDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedUserPassword
     * @param  EncryptedClientDataDto|false  $encryptedPassword
     * @param  EncryptedClientDataDto|false|null  $encryptedUsername
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Valid
        ]
        public EncryptedClientDataDto $encryptedUserPassword,

        public EncryptedClientDataDto|false $encryptedPassword = false,

        public EncryptedClientDataDto|null|false $encryptedUsername = false
    ) {
    }
}
