<?php
/**  */

/**
 * @author bsteffan
 * @since 2025-09-16
 * @noinspection PhpMultipleClassDeclarationsInspection Attribute
 */

namespace App\Service\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class PatchConfiguration
{
    /**
     * @param  string  $getter
     * @param  string  $setter
     */
    public function __construct(
        private string $getter,
        private string $setter
    ) {
    }

    /**
     * @return string
     */
    public function getGetter(): string
    {
        return $this->getter;
    }

    /**
     * @return string
     */
    public function getSetter(): string
    {
        return $this->setter;
    }
}
