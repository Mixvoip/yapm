<?php

/**
 * @author bsteffan
 * @since 2025-06-27
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class EncryptedClientDataDto
{
    /**
     * @param  string  $encryptedData
     * @param  string  $clientPublicKey
     * @param  string  $nonce
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $encryptedData,

        #[Assert\NotBlank]
        public string $clientPublicKey,

        #[Assert\NotBlank]
        public string $nonce
    ) {
    }
}
