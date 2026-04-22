<?php

/**
 * @author bsteffan
 * @since 2026-03-27
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ImportPasswordItemDto
{
    /**
     * @param  string  $title
     * @param  string  $vaultId
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  EncryptedClientDataDto|null  $encryptedUsername
     * @param  string|null  $target
     * @param  string|null  $location
     * @param  string|null  $externalId
     * @param  string|null  $folderId
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Length(max: 255),
            Assert\Type('string'),
        ]
        public string $title,

        #[
            Assert\NotBlank,
            Assert\Type('string'),
        ]
        public string $vaultId,

        #[
            Assert\NotBlank,
            Assert\Valid,
        ]
        public EncryptedClientDataDto $encryptedPassword,

        #[Assert\Valid]
        public ?EncryptedClientDataDto $encryptedUsername = null,

        #[
            Assert\Type('string'),
            Assert\Length(max: 255),
        ]
        public ?string $target = null,

        #[
            Assert\Type('string'),
            Assert\Length(max: 255),
        ]
        public ?string $location = null,

        #[
            Assert\Type('string'),
            Assert\Length(max: 255),
        ]
        public ?string $externalId = null,

        #[Assert\Type('string')]
        public ?string $folderId = null,
    ) {
    }
}
