<?php

/**
 * @author bsteffan
 * @since 2025-09-16
 */

namespace App\Controller\Vault\Dto;

use App\Entity\Enums\FolderField;
use App\Entity\Enums\PasswordField;
use Symfony\Component\Validator\Constraints as Assert;

class PatchDto
{
    /**
     * @param  string  $name
     * @param  FolderField[]|null  $mandatoryFolderFields
     * @param  PasswordField[]|null  $mandatoryPasswordFields
     * @param  string  $iconName
     * @param  bool  $allowPasswordsAtRoot
     */
    public function __construct(
        #[
            Assert\NotBlank(normalizer: "trim"),
            Assert\Length(min: 1, max: 255),
        ]
        public readonly string $name = "a",

        #[Assert\All(
            new Assert\Choice(callback: [FolderField::class, "cases"]),
        )]
        public ?array $mandatoryFolderFields = null,

        #[Assert\All(
            new Assert\Choice(callback: [PasswordField::class, "cases"]),
        )]
        public ?array $mandatoryPasswordFields = null,

        #[Assert\NotBlank(normalizer: "trim")]
        public readonly string $iconName = "a",

        public readonly bool $allowPasswordsAtRoot = false
    ) {
        if ($this->mandatoryFolderFields === []) {
            $this->mandatoryFolderFields = null;
        }

        if ($this->mandatoryPasswordFields === []) {
            $this->mandatoryPasswordFields = null;
        }
    }
}
