<?php

/**
 * @author bsteffan
 * @since 2026-03-27
 */

namespace App\Controller\Password\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ImportPasswordsDto
{
    /**
     * @param  ImportPasswordItemDto[]  $items
     */
    public function __construct(
        #[
            Assert\NotBlank,
            Assert\Type('array'),
            Assert\Valid,
            Assert\Count(min: 1, max: 100),
            Assert\All([
                new Assert\Type(ImportPasswordItemDto::class),
            ]),
        ]
        public array $items,
    ) {
    }
}
