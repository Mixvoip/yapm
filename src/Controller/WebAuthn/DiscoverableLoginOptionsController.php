<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Service\WebAuthn\WebAuthnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DiscoverableLoginOptionsController extends AbstractController
{
    /**
     * Get WebAuthn login options for discoverable credential (usernameless) login.
     * Public endpoint — no JWT or email required.
     * Returns options with empty allowCredentials so the browser shows all
     * resident credentials for the relying party.
     *
     * @param  WebAuthnService  $webAuthnService
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/login/discoverable/options',
        name: 'api_webauthn_login_discoverable_options',
        methods: ['GET']
    )]
    public function index(WebAuthnService $webAuthnService): JsonResponse
    {
        $result = $webAuthnService->generateDiscoverableLoginOptions();

        return new JsonResponse([
            'sessionToken' => $result['sessionToken'],
            'publicKeyOptions' => json_decode($result['options'], true),
        ]);
    }
}
