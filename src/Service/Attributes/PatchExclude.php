<?php

/**
 * @author bsteffan
 * @since 2025-09-16
 * @noinspection PhpMultipleClassDeclarationsInspection Attribute
 */

namespace App\Service\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class PatchExclude
{
}
