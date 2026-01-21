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
     * @param  UserPermissionWithPartialDto[]  $userPermissions
     * @param  bool  $cascade
     */
    public function __construct(
        #[Assert\Valid]
        public EncryptedClientDataDto $encryptedPassword,

        #[
            Assert\Valid,
            Assert\All([
                new Assert\Type(GroupPermissionWithPartialDto::class),
            ])
        ]
        public array $groups = [],

        #[
            Assert\Valid,
            Assert\All([
                new Assert\Type(UserPermissionWithPartialDto::class),
            ])
        ]
        public array $userPermissions = [],

        #[Assert\Type('bool')]
        public bool $cascade = false
    ) {
    }
}
