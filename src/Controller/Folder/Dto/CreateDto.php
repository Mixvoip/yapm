<?php

/**
 * @author bsteffan
 * @since 2025-06-10
 */

namespace App\Controller\Folder\Dto;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\Dto\UserPermissionDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateDto
{
    /**
     * @param  string  $name
     * @param  string  $vaultId
     * @param  string|null  $externalId
     * @param  string  $iconName
     * @param  string|null  $description
     * @param  string|null  $parentFolderId
     * @param  GroupPermissionDto[]  $groups
     * @param  UserPermissionDto[]  $userPermissions
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Length(max: 128),
        ]
        public string $name,

        #[Assert\NotBlank]
        public string $vaultId,

        public ?string $externalId = null,

        #[Assert\NotBlank(normalizer: "trim")]
        public string $iconName = "folder",

        public ?string $description = null,

        public ?string $parentFolderId = null,

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
