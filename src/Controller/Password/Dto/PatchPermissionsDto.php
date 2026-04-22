<?php

/**
 * @author bsteffan
 * @since 2025-08-12
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\AuthenticationDataDto;
use App\Controller\Dto\GroupPermissionDto;
use App\Controller\Dto\UserPermissionDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchPermissionsDto
{
    /**
     * @param  AuthenticationDataDto  $authData
     * @param  GroupPermissionDto[]  $groups
     * @param  UserPermissionDto[]  $userPermissions
     */
    public function __construct(
        #[Assert\Valid]
        public AuthenticationDataDto $authData,

        #[
            Assert\Valid,
            Assert\All([
                new Assert\Type(GroupPermissionDto::class),
            ])
        ]
        public array $groups = [],

        #[
            Assert\Valid,
            Assert\All([
                new Assert\Type(UserPermissionDto::class),
            ])
        ]
        public array $userPermissions = [],
    ) {
    }
}
