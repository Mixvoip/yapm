<?php

/**
 * @author bsteffan
 * @since 2026-03-12
 */

namespace App\Controller\User\Dto;

use App\Controller\Dto\AuthenticationDataDto;
use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ChangePasswordDto
{
    /**
     * @param  AuthenticationDataDto  $authData  Current password/PRF authentication
     * @param  EncryptedClientDataDto  $newEncryptedPassword  New password encrypted with server public key
     */
    public function __construct(
        #[Assert\Valid]
        public AuthenticationDataDto $authData,

        #[Assert\Valid]
        public EncryptedClientDataDto $newEncryptedPassword,
    ) {
    }
}
