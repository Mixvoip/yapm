<?php

/**
 * @author bsteffan
 * @since 2025-06-10
 */

namespace App\Controller\Folder\Dto;

use App\Controller\Dto\GroupPermissionDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateDto
{
    /**
     * @param  string  $name
     * @param  string  $vaultId
     * @param  string|null  $externalId
     * @param  string|null  $parentFolderId
     * @param  GroupPermissionDto[]  $groups
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Length(max: 128),
            Assert\Type('string'),
        ]
        private string $name,

        #[
            Assert\NotBlank,
            Assert\Type('string'),
        ]
        private string $vaultId,

        #[
            Assert\Type('string'),
        ]
        private ?string $externalId = null,

        #[Assert\Type('string')]
        private ?string $parentFolderId = null,

        #[
            Assert\Type('array'),
            Assert\Valid,
            Assert\All([
                new Assert\Type(GroupPermissionDto::class),
            ])
        ]
        private array $groups = [],
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        if ($this->externalId === "") {
            return null;
        }

        return $this->externalId;
    }

    /**
     * @return string
     */
    public function getVaultId(): string
    {
        return $this->vaultId;
    }

    /**
     * @return string|null
     */
    public function getParentFolderId(): ?string
    {
        return $this->parentFolderId;
    }

    /**
     * @return GroupPermissionDto[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
