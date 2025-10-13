<?php

namespace App\Controller\User\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchDto
{
    /**
     * @param  bool|null  $admin
     * @param  string[]|null  $groups
     */
    public function __construct(
        #[Assert\Type('bool')]
        private ?bool $admin = null,

        #[
            Assert\Type('array'),
            Assert\All([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ])
        ]
        private ?array $groups = null
    ) {
    }

    /**
     * @return bool|null
     */
    public function isAdmin(): ?bool
    {
        return $this->admin;
    }

    /**
     * @return string[]|null
     */
    public function getGroups(): ?array
    {
        return $this->groups;
    }
}
