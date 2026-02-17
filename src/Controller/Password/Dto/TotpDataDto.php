<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Entity\Enums\TotpAlgorithm;
use App\Entity\Enums\TotpDigits;
use App\Entity\Enums\TotpPeriod;
use Symfony\Component\Validator\Constraints as Assert;

readonly class TotpDataDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedSecretKey
     * @param  TotpAlgorithm  $algorithm
     * @param  TotpPeriod  $period
     * @param  TotpDigits  $digits
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Valid
        ]
        public EncryptedClientDataDto $encryptedSecretKey,

        #[Assert\NotBlank]
        public TotpAlgorithm $algorithm,

        #[Assert\NotBlank]
        public TotpPeriod $period,

        #[Assert\NotBlank]
        public TotpDigits $digits
    ) {
    }
}
