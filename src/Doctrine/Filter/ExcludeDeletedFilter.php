<?php

/**
 * @author bsteffan
 * @since 2025-10-02
 */

namespace App\Doctrine\Filter;

use App\Entity\DeletableEntity;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class ExcludeDeletedFilter extends SQLFilter
{
    /**
     * @inheritDoc
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!is_subclass_of($targetEntity->getName(), DeletableEntity::class)) {
            return "";
        }

        if (!$targetEntity->hasField('deletedAt')) {
            return "";
        }

        return sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
