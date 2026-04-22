<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Controller\EncryptionAwareTrait;
use App\Entity\User;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Encryption\EncryptionService;
use App\Service\WebAuthn\WebAuthnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class GetRegistrationOptionsController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Get WebAuthn registration options for the authenticated user.
     * Returns the public key creation options, a PRF salt for key derivation,
     * and the user's encrypted keys (so the client can decrypt the private key during registration).
     *
     * @param  WebAuthnService  $webAuthnService
     * @param  EncryptionService  $encryptionService
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  WebAuthnCredentialRepository  $webAuthnCredentialRepository
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/register/options',
        name: 'api_webauthn_register_options',
        methods: ['GET']
    )]
    public function index(
        WebAuthnService $webAuthnService,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
        WebAuthnCredentialRepository $webAuthnCredentialRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $this->encryptionService = $encryptionService;
        $this->passwordHasher = $passwordHasher;
        $this->webAuthnCredentialRepository = $webAuthnCredentialRepository;

        $result = $webAuthnService->generateRegistrationOptions($user);

        return new JsonResponse([
            'publicKeyOptions' => json_decode($result['options'], true),
            'prfSalt' => $result['prfSalt'],
            'userKeys' => $this->buildUserKeysResponse($user),
        ]);
    }
}
