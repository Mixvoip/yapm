<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Controller\WebAuthn;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\User;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class DeleteCredentialController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Delete a WebAuthn credential. Requires master password verification.
     *
     * @param  string  $id
     * @param  EncryptedClientDataDto  $encryptedPassword
     * @param  WebAuthnCredentialRepository  $credentialRepository
     * @param  EncryptionService  $encryptionService
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     */
    #[Route(
        '/webauthn/credentials/{id}/delete',
        name: 'api_webauthn_credentials_delete',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['POST']
    )]
    public function index(
        string $id,
        #[MapRequestPayload] EncryptedClientDataDto $encryptedPassword,
        WebAuthnCredentialRepository $credentialRepository,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $this->encryptionService = $encryptionService;
        $this->passwordHasher = $passwordHasher;

        $credential = $credentialRepository->find($id);

        if (is_null($credential) || $credential->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException("Credential with id: $id not found.");
        }

        // Verify master password
        try {
            $this->decryptUserPrivateKey($encryptedPassword);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Authentication Error',
                'message' => $e->getMessage(),
            ], 401);
        }

        $entityManager->remove($credential);
        $entityManager->flush();

        return $this->json(null, 204);
    }
}
