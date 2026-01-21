<?php

/**
 * @author bsteffan
 * @since 2025-12-24
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UserPermissionDto
{
    /**
     * @param  string  $userId
     * @param  bool  $canWrite
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        public string $userId,

        #[Assert\NotNull]
        #[Assert\Type('bool')]
        public bool $canWrite
    ) {
    }
}
