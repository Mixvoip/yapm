<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Controller\WebAuthn\Dto\LoginVerifyDto;
use App\Entity\Enums\AuditAction;
use App\Security\UserChecker;
use App\Service\Audit\AuditService;
use App\Service\WebAuthn\WebAuthnService;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class LoginVerifyController extends AbstractController
{
    /**
     * Verify the WebAuthn login assertion and return JWT tokens.
     * Public endpoint — no JWT required.
     *
     * @param  LoginVerifyDto  $dto
     * @param  WebAuthnService  $webAuthnService
     * @param  JWTTokenManagerInterface  $jwtManager
     * @param  RefreshTokenGeneratorInterface  $refreshTokenGenerator
     * @param  RefreshTokenManagerInterface  $refreshTokenManager
     * @param  UserChecker  $userChecker
     * @param  AuditService  $auditService
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/login/verify',
        name: 'api_webauthn_login_verify',
        methods: ['POST']
    )]
    public function index(
        #[MapRequestPayload] LoginVerifyDto $dto,
        WebAuthnService $webAuthnService,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        UserChecker $userChecker,
        AuditService $auditService
    ): JsonResponse {
        try {
            $webAuthnCredential = $webAuthnService->verifyLoginAssertion(
                json_encode($dto->credential),
                $dto->sessionToken
            );
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'Authentication Error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = $webAuthnCredential->getUser();

        // Run pre-auth checks (active, etc.)
        try {
            $userChecker->checkPreAuth($user);
        } catch (CustomUserMessageAccountStatusException $e) {
            return $this->json([
                'error' => 'Authentication Error',
                'message' => $e->getMessageKey(),
            ], 401);
        }

        // Create JWT token
        $token = $jwtManager->create($user);

        // Create refresh token (TTL: 7 days = 604800 seconds)
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, 604800);
        $refreshTokenManager->save($refreshToken);

        $auditService->log(AuditAction::PasskeyLogin, $user, user: $user);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ]);
    }
}
