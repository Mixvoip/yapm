<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Controller\EncryptionAwareTrait;
use App\Controller\WebAuthn\Dto\VerifyRegistrationDto;
use App\Entity\User;
use App\Entity\WebAuthnCredential;
use App\Service\Encryption\EncryptionService;
use App\Service\WebAuthn\WebAuthnService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class VerifyRegistrationController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Verify the WebAuthn registration response and store the credential.
     * Requires master password verification.
     *
     * @param  VerifyRegistrationDto  $dto
     * @param  WebAuthnService  $webAuthnService
     * @param  EncryptionService  $encryptionService
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/register/verify',
        name: 'api_webauthn_register_verify',
        methods: ['POST']
    )]
    public function index(
        #[MapRequestPayload] VerifyRegistrationDto $dto,
        WebAuthnService $webAuthnService,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $this->encryptionService = $encryptionService;
        $this->passwordHasher = $passwordHasher;

        // Verify master password
        try {
            $this->decryptUserPrivateKey($dto->encryptedPassword);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Authentication Error',
                'message' => $e->getMessage(),
            ], 401);
        }

        // Verify the WebAuthn attestation response (also verifies PRF salt integrity)
        try {
            $source = $webAuthnService->verifyRegistration(
                $user,
                json_encode($dto->credential),
                $dto->prfSalt
            );
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Registration Error',
                'message' => $e->getMessage(),
            ], 400);
        }

        // Create and persist the credential entity
        $credential = new WebAuthnCredential();
        $credential->setCreatedBy($user->getUsername());
        $credential->setUser($user);
        $credential->setCredentialId($source->publicKeyCredentialId);
        $credential->setPublicKeyCredentialSource($webAuthnService->serializeSource($source));
        $credential->setName($dto->name);
        $credential->setCacheOnUse($dto->cacheOnUse);
        $credential->setPrfSalt($dto->prfSalt);
        $credential->setPrfEncryptedPrivateKey($dto->prfEncryptedPrivateKey);
        $credential->setPrfPrivateKeyNonce($dto->prfPrivateKeyNonce);

        $entityManager->persist($credential);
        $entityManager->flush();

        return $this->json([
            'id' => $credential->getId(),
            'name' => $credential->getName(),
        ], 201);
    }
}
