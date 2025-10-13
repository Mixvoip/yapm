<?php

/**
 * @author bsteffan
 * @since 2025-09-10
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchPermissionsWithPartialDto
{
    /**
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  GroupPermissionWithPartialDto[]  $groups
     * @param  bool  $cascade
     */
    public function __construct(
        #[Assert\Valid]
        public EncryptedClientDataDto $encryptedPassword,

        #[Assert\Count(min: 1, minMessage: 'At least one group must be provided.')]
        #[Assert\All([
            new Assert\Type(GroupPermissionWithPartialDto::class),
        ])]
        public array $groups,

        #[Assert\Type('bool')]
        public bool $cascade = false
    ) {
    }
}
