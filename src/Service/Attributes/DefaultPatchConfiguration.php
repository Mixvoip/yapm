<?php
/**  */

/**
 * @author bsteffan
 * @since 2025-09-16
 * @noinspection PhpMultipleClassDeclarationsInspection Attribute
 */

namespace App\Service\Attributes;

use Attribute;
use Closure;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DefaultPatchConfiguration
{
    private ?Closure $normalizer;

    /**
     * @param  bool  $ignore
     * @param  callable|null  $normalizer
     */
    public function __construct(
        private bool $ignore = false,
        ?callable $normalizer = null
    ) {
        if (is_null($normalizer)) {
            $this->normalizer = null;
        } else {
            $this->normalizer = $normalizer(...);
        }
    }

    /**
     * @return bool
     */
    public function isIgnore(): bool
    {
        return $this->ignore;
    }

    /**
     * @return callable|null
     */
    public function getNormalizer(): ?callable
    {
        return $this->normalizer;
    }
}
