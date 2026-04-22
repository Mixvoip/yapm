<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class LoginVerifyDto
{
    /**
     * @param  string  $sessionToken  The session token from the login options response
     * @param  array  $credential  The serialized PublicKeyCredential from the browser
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $sessionToken,

        #[Assert\NotBlank]
        public array $credential
    ) {
    }
}
