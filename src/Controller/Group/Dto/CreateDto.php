<?php

/**
 * @author bsteffan
 * @since 2025-05-26
 */

namespace App\Controller\Group\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateDto
{
    /**
     * @param  string  $name
     * @param  string[]  $managers
     * @param  string[]  $users
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $name,

        #[
            Assert\All([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ])
        ]
        public array $managers,

        #[
            Assert\All([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ])
        ]
        public array $users = []
    ) {
    }
}
