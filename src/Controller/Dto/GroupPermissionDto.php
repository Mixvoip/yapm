<?php

/**
 * @author bsteffan
 * @since 2025-09-01
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class GroupPermissionDto
{
    /**
     * @param  string  $groupId
     * @param  bool  $canWrite
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        public string $groupId,

        #[Assert\NotNull]
        #[Assert\Type('bool')]
        public bool $canWrite
    ) {
    }
}
