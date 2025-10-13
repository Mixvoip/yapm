<?php

/**
 * @author bsteffan
 * @since 2025-08-12
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\Dto\GroupPermissionDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchPermissionsDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  GroupPermissionDto[]  $groups
     */
    public function __construct(
        #[Assert\Valid]
        public EncryptedClientDataDto $encryptedPassword,

        #[Assert\Count(min: 1, minMessage: 'At least one group must be provided.')]
        #[Assert\All([
            new Assert\Type(GroupPermissionDto::class),
        ])]
        public array $groups,
    ) {
    }
}
