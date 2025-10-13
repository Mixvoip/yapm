<?php

/**
 * @author bsteffan
 * @since 2025-09-15
 */

namespace App\Controller\Vault\Dto;

use App\Controller\Dto\GroupPermissionDto;
use App\Entity\Enums\FolderField;
use App\Entity\Enums\PasswordField;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateDto
{
    /**
     * @param  string  $name
     * @param  GroupPermissionDto[]  $groups
     * @param  FolderField[]  $mandatoryFolderFields
     * @param  PasswordField[]  $mandatoryPasswordFields
     * @param  string  $iconName
     * @param  bool  $allowPasswordsAtRoot
     */
    public function __construct(
        #[
            Assert\NotBlank(normalizer: "trim"),
            Assert\Length(min: 1, max: 255),
        ]
        public string $name,

        #[
            Assert\NotBlank,
            Assert\All(
                new Assert\Type(GroupPermissionDto::class),
            )
        ]
        public array $groups,

        #[Assert\All(
            new Assert\Choice(callback: [FolderField::class, "cases"]),
        )]
        public array $mandatoryFolderFields = [],

        #[Assert\All(
            new Assert\Choice(callback: [PasswordField::class, "cases"]),
        )]
        public array $mandatoryPasswordFields = [],

        #[Assert\NotBlank(normalizer: "trim")]
        public string $iconName = "folder",

        public bool $allowPasswordsAtRoot = true
    ) {
    }
}
