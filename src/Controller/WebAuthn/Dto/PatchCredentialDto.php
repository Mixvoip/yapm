<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn\Dto;

readonly class PatchCredentialDto
{
    /**
     * @param  bool  $cacheOnUse
     */
    public function __construct(
        public bool $cacheOnUse = true
    ) {
    }
}
