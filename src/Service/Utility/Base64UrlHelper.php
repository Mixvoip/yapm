<?php

/**
 * @author bsteffan
 * @since 2025-04-28
 */

namespace App\Service\Utility;

class Base64UrlHelper
{
    /**
     * Base64 Url encode the given data.
     *
     * @param  string  $data
     *
     * @return string
     */
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 Url decode the given data.
     *
     * @param  string  $data
     *
     * @return string
     */
    public static function decode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        return base64_decode($data);
    }
}
