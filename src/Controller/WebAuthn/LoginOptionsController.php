<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Controller\WebAuthn\Dto\LoginOptionsDto;
use App\Repository\UserRepository;
use App\Service\WebAuthn\WebAuthnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class LoginOptionsController extends AbstractController
{
    /**
     * Get WebAuthn login options for an unauthenticated user.
     * Public endpoint — no JWT required.
     *
     * @param  LoginOptionsDto  $dto
     * @param  UserRepository  $userRepository
     * @param  WebAuthnService  $webAuthnService
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/login/options',
        name: 'api_webauthn_login_options',
        methods: ['POST']
    )]
    public function index(
        #[MapRequestPayload] LoginOptionsDto $dto,
        UserRepository $userRepository,
        WebAuthnService $webAuthnService
    ): JsonResponse {
        $user = $userRepository->findOneBy(['email' => $dto->email]);

        if (is_null($user)) {
            // Don't reveal whether the user exists
            return $this->json([
                'error' => 'Login Error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        try {
            $result = $webAuthnService->generateLoginOptions($user);
        } catch (\RuntimeException) {
            // User has no passkeys — same generic error to avoid enumeration
            return $this->json([
                'error' => 'Login Error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return new JsonResponse([
            'sessionToken' => $result['sessionToken'],
            'publicKeyOptions' => json_decode($result['options'], true),
            'prfSalts' => $result['prfSalts'],
        ]);
    }
}
