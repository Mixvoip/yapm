<?php

/**
 * @author bsteffan
 * @since 2025-04-25
 */

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class CustomAccessDeniedHandler implements AccessDeniedHandlerInterface
{

    /**
     * @inheritDoc
     */
    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        return new Response(json_encode([
            'error' => 'Access Denied',
        ]), Response::HTTP_FORBIDDEN);
    }
}
