<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Entity\User;
use App\Service\WebAuthn\WebAuthnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetAuthenticationOptionsController extends AbstractController
{
    /**
     * Get WebAuthn authentication options for an authenticated user.
     * Used when the user wants to authenticate a sensitive operation with a passkey.
     * Returns the public key request options and PRF salts per credential.
     *
     * @param  WebAuthnService  $webAuthnService
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/authenticate/options',
        name: 'api_webauthn_authenticate_options',
        methods: ['GET']
    )]
    public function index(WebAuthnService $webAuthnService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $webAuthnService->generateAuthenticationOptions($user);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'No Passkeys',
                'message' => $e->getMessage(),
            ], 404);
        }

        return new JsonResponse([
            'publicKeyOptions' => json_decode($result['options'], true),
            'prfSalts' => $result['prfSalts'],
            'credentialSettings' => $result['credentialSettings'],
        ]);
    }
}
