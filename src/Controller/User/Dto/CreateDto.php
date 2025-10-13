<?php

namespace App\Controller\User\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateDto
{
    /**
     * @param  string  $email
     * @param  string  $username
     * @param  bool  $admin
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Email,
        ]
        private string $email,

        #[
            Assert\NotBlank,
            Assert\Type('string'),
        ]
        private string $username,

        #[
            Assert\NotNull,
            Assert\Type('bool'),
        ]
        private bool $admin
    ) {
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->admin;
    }
}
