<?php

/**
 * @author bsteffan
 * @since 2025-09-16
 */

namespace App\Controller\Vault\Dto;

use App\Controller\NulledValueGetterTrait;
use App\Entity\Enums\FolderField;
use App\Entity\Enums\PasswordField;
use App\Service\Attributes\DefaultPatchConfiguration;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchDto
{
    use NulledValueGetterTrait;

    /**
     * @param  string  $name
     * @param  FolderField[]|null  $mandatoryFolderFields
     * @param  PasswordField[]|null  $mandatoryPasswordFields
     * @param  string  $iconName
     * @param  bool  $allowPasswordsAtRoot
     * @param  string|null  $description
     */
    public function __construct(
        #[
            Assert\NotBlank(normalizer: "trim"),
            Assert\Length(min: 1, max: 255),
        ]
        public string $name = "a",

        #[
            Assert\All(
                new Assert\Choice(callback: [FolderField::class, "cases"]),
            ),
            DefaultPatchConfiguration(normalizer: [self::class, "getArrayOrNull"])
        ]
        public ?array $mandatoryFolderFields = null,

        #[
            Assert\All(
                new Assert\Choice(callback: [PasswordField::class, "cases"]),
            ),
            DefaultPatchConfiguration(normalizer: [self::class, "getArrayOrNull"])
        ]
        public ?array $mandatoryPasswordFields = null,

        #[Assert\NotBlank(normalizer: "trim")]
        public string $iconName = "a",

        public bool $allowPasswordsAtRoot = false,

        #[DefaultPatchConfiguration(normalizer: [self::class, "getTrimmedOrNull"])]
        public ?string $description = null
    ) {
    }
}
