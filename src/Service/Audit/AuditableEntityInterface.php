<?php

/**
 * @author bsteffan
 * @since 2025-07-14
 */

namespace App\Service\Audit;

interface AuditableEntityInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * String representation of the entity.
     *
     * @return string
     */
    public function __toString(): string;
}
