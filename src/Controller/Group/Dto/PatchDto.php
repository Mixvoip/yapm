<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Controller\Group\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  string[]|null  $managers
     * @param  string[]|null  $users
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Valid
        ]
        private EncryptedClientDataDto $encryptedPassword,

        #[
            Assert\Type('array'),
            Assert\All([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ])
        ]
        private ?array $managers = null,

        #[
            Assert\Type('array'),
            Assert\All([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ])
        ]
        private ?array $users = null,
    ) {
    }

    /**
     * @return EncryptedClientDataDto
     */
    public function getEncryptedPassword(): EncryptedClientDataDto
    {
        return $this->encryptedPassword;
    }

    /**
     * @return string[]|null
     */
    public function getManagers(): ?array
    {
        return $this->managers;
    }

    /**
     * @return string[]|null
     */
    public function getUsers(): ?array
    {
        return $this->users;
    }
}
