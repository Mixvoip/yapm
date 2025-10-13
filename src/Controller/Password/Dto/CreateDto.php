<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Password\Dto;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\Dto\GroupPermissionDto;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateDto
{
    /**
     * @param  string  $title
     * @param  string  $vaultId
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  EncryptedClientDataDto|null  $encryptedUsername
     * @param  string|null  $target
     * @param  string|null  $description
     * @param  string|null  $location
     * @param  string|null  $externalId
     * @param  string|null  $folderId
     * @param  GroupPermissionDto[]  $groups
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Length(max: 255),
            Assert\Type('string'),
        ]
        private string $title,

        #[
            Assert\NotBlank,
            Assert\Type('string'),
        ]
        private string $vaultId,

        #[
            Assert\NotBlank,
            Assert\Valid
        ]
        private EncryptedClientDataDto $encryptedPassword,

        #[Assert\Valid]
        private ?EncryptedClientDataDto $encryptedUsername = null,

        #[
            Assert\Type('string'),
            Assert\Length(max: 255),
        ]
        private ?string $target = null,

        #[
            Assert\Type('string'),
        ]
        private ?string $description = null,

        #[
            Assert\Type('string'),
            Assert\Length(max: 255),
        ]
        private ?string $location = null,

        #[
            Assert\Type('string'),
            Assert\Length(max: 255),
        ]
        private ?string $externalId = null,

        #[Assert\Type('string')]
        private ?string $folderId = null,

        #[
            Assert\Type('array'),
            Assert\Valid,
            Assert\All([
                new Assert\Type(GroupPermissionDto::class),
            ])
        ]
        private array $groups = []
    ) {
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getVaultId(): string
    {
        return $this->vaultId;
    }

    /**
     * @return EncryptedClientDataDto
     */
    public function getEncryptedPassword(): EncryptedClientDataDto
    {
        return $this->encryptedPassword;
    }

    /**
     * @return EncryptedClientDataDto|null
     */
    public function getEncryptedUsername(): ?EncryptedClientDataDto
    {
        return $this->encryptedUsername;
    }

    /**
     * @return string|null
     */
    public function getTarget(): ?string
    {
        if ($this->target === "") {
            return null;
        }

        return $this->target;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        if ($this->location === "") {
            return null;
        }

        return $this->location;
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
     * @return string|null
     */
    public function getDescription(): ?string
    {
        if ($this->description === "") {
            return null;
        }

        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getFolderId(): ?string
    {
        return $this->folderId;
    }

    /**
     * @return GroupPermissionDto[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
