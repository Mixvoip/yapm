<?php

/**
 * @author bsteffan
 * @since 2025-12-24
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UserPermissionWithPartialDto extends UserPermissionDto
{
    /**
     * @inheritDoc
     *
     * @param  bool  $partial
     */
    public function __construct(
        string $userId,

        bool $canWrite,

        #[Assert\NotNull]
        public bool $partial
    ) {
        parent::__construct($userId, $canWrite);
    }
}
