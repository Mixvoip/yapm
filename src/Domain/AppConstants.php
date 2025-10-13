<?php

/**
 * @author bsteffan
 * @since 2025-04-23
 */

namespace App\Domain;

class AppConstants
{
    // GLOBAL ENV CONSTANTS (LOADED THROUGH KERNEL)
    public static string $apiBaseUri = "";
    public static string $frontendBaseUri = "";
    public static string $mailerFromAddress = "";
    // END OF GLOBAL ENV CONSTANTS

    public const string DATE_FORMAT = 'Y-m-d H:i:s';
    public const string PERIOD_FORMAT = 'Y-m';

    public const string EXCLUDE_DELETED_FILTER = "exclude_deleted";

    /**
     * Determine if the application is running in test mode.
     *
     * @return bool
     */
    public static function isTest(): bool
    {
        return $_ENV['APP_ENV'] === 'test';
    }
}
