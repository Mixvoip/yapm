<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Password\Dto;

use App\Controller\NulledValueGetterTrait;
use App\Service\Attributes\DefaultPatchConfiguration;
use Symfony\Component\Validator\Constraints as Assert;

readonly class PatchDto
{
    use NulledValueGetterTrait;

    /**
     * @param  string  $title
     * @param  string|null  $description
     * @param  string|null  $target
     * @param  string|null  $location
     * @param  string|null  $externalId
     */
    public function __construct(
        #[
            Assert\NotBlank(normalizer: "trim"),
            Assert\Length(max: 255),
        ]
        public string $title = "a",

        #[DefaultPatchConfiguration(normalizer: [self::class, "getTrimmedOrNull"])]
        public ?string $description = null,

        #[DefaultPatchConfiguration(ignore: true)]
        public ?string $target = null,

        #[DefaultPatchConfiguration(ignore: true)]
        public ?string $location = null,

        #[DefaultPatchConfiguration(ignore: true)]
        public ?string $externalId = null
    ) {
    }
}
