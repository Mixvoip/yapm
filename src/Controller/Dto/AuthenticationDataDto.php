<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Callback('validate')]
readonly class AuthenticationDataDto
{
    /**
     * @param  EncryptedClientDataDto|null  $encryptedPassword  Password authentication path
     * @param  PrfClientDataDto|null  $prfData  PRF authentication path
     */
    public function __construct(
        #[Assert\Valid]
        public ?EncryptedClientDataDto $encryptedPassword = null,

        #[Assert\Valid]
        public ?PrfClientDataDto $prfData = null
    ) {
    }

    /**
     * Validate that exactly one authentication method is provided.
     *
     * @param  \Symfony\Component\Validator\Context\ExecutionContextInterface  $context
     *
     * @return void
     */
    public function validate(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (is_null($this->encryptedPassword) && is_null($this->prfData)) {
            $context->buildViolation('Either encryptedPassword or prfData must be provided.')
                    ->addViolation();
        }

        if (!is_null($this->encryptedPassword) && !is_null($this->prfData)) {
            $context->buildViolation('Only one of encryptedPassword or prfData may be provided.')
                    ->addViolation();
        }
    }
}
