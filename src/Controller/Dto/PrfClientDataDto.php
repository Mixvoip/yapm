<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class PrfClientDataDto
{
    /**
     * @param  string  $credentialId  Base64-encoded credential ID
     * @param  string  $encryptedData  PRF-derived key encrypted for server transport
     * @param  string  $clientPublicKey  Transport encryption public key
     * @param  string  $nonce  Transport encryption nonce
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $credentialId,

        #[Assert\NotBlank]
        public string $encryptedData,

        #[Assert\NotBlank]
        public string $clientPublicKey,

        #[Assert\NotBlank]
        public string $nonce
    ) {
    }
}
