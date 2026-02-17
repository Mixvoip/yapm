<?php

/**
 * @author bsteffan
 * @since 2025-08-06
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Service\Attributes\DefaultPatchConfiguration;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchSensitiveDataDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedUserPassword
     * @param  EncryptedClientDataDto|null  $encryptedPassword
     * @param  EncryptedClientDataDto|null  $encryptedUsername
     * @param  TotpDataDto|null  $totp  Set complete TOTP (all fields) or null to clear
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Valid,
            DefaultPatchConfiguration(ignore: true)
        ]
        public EncryptedClientDataDto $encryptedUserPassword,

        #[
            Assert\Valid,
            DefaultPatchConfiguration(ignore: true)
        ]
        public ?EncryptedClientDataDto $encryptedPassword = null,

        #[
            Assert\Valid,
            DefaultPatchConfiguration(ignore: true)
        ]
        public ?EncryptedClientDataDto $encryptedUsername = null,

        #[DefaultPatchConfiguration(ignore: true)]
        public ?TotpDataDto $totp = null
    ) {
    }
}
