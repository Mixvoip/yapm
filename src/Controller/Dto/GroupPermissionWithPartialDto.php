<?php

/**
 * @author bsteffan
 * @since 2025-09-08
 */

namespace App\Controller\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class GroupPermissionWithPartialDto extends GroupPermissionDto
{
    /**
     * @inheritDoc
     *
     * @param  bool  $partial
     */
    public function __construct(
        string $groupId,

        bool $canWrite,

        #[Assert\NotNull]
        public bool $partial
    ) {
        parent::__construct($groupId, $canWrite);
    }
}
