<?php

/**
 * @author bsteffan
 * @since 2025-10-17
 */

namespace App\Controller;

trait NulledValueGetterTrait
{
    /**
     * Return a trimmed value. If the trimmed string is empty, this returns null instead.
     *
     * @param  string|null  $value
     *
     * @return string|null
     */
    public static function getTrimmedOrNull(?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $value = trim($value);

        return $value === "" ? null : $value;
    }

    /**
     * Return the array itself if not empty. If empty, return null.
     *
     * @param  array|null  $value
     *
     * @return array|null
     */
    public static function getArrayOrNull(?array $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        return $value;
    }
}
