<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class LoginOptionsDto
{
    /**
     * @param  string  $email
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email
    ) {
    }
}
