<?php

namespace App\Controller\User\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class RegisterDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedPassword
     */
    public function __construct(
        #[Assert\Valid]
        private EncryptedClientDataDto $encryptedPassword,
    ) {
    }

    /**
     * @return EncryptedClientDataDto
     */
    public function getEncryptedPassword(): EncryptedClientDataDto
    {
        return $this->encryptedPassword;
    }
}
