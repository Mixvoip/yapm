<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class VerifyRegistrationDto
{
    /**
     * @param  array  $credential  The serialized PublicKeyCredential from the browser
     * @param  string  $name  User-provided name for this passkey
     * @param  string  $prfSalt  Base64-encoded PRF salt used during registration
     * @param  string  $prfEncryptedPrivateKey  User's private key re-encrypted with the PRF-derived key
     * @param  string  $prfPrivateKeyNonce  Nonce used for PRF encryption
     * @param  EncryptedClientDataDto  $encryptedPassword  Master password for verification
     */
    public function __construct(
        #[Assert\NotBlank]
        public array $credential,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        public string $prfSalt,

        #[Assert\NotBlank]
        public string $prfEncryptedPrivateKey,

        #[Assert\NotBlank]
        public string $prfPrivateKeyNonce,

        #[Assert\Valid]
        public EncryptedClientDataDto $encryptedPassword,

        public bool $cacheOnUse = true,
    ) {
    }
}
