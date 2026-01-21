<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Folder\Dto;

use App\Controller\NulledValueGetterTrait;
use App\Service\Attributes\DefaultPatchConfiguration;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchDto
{
    use NulledValueGetterTrait;

    /**
     * @param  string  $name
     * @param  string|null  $externalId
     * @param  string  $iconName
     * @param  string|null  $description
     */
    public function __construct(
        #[
            Assert\NotBlank(normalizer: "trim"),
            Assert\Length(min: 1, max: 128),
        ]
        public string $name = "a",

        #[DefaultPatchConfiguration(ignore: true)]
        public ?string $externalId = null,

        #[Assert\NotBlank(normalizer: "trim")]
        public string $iconName = "a",

        #[DefaultPatchConfiguration(normalizer: [self::class, "getTrimmedOrNull"])]
        public ?string $description = null
    ) {
    }
}
