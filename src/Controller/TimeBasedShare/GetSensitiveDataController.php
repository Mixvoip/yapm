<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Controller\TimeBasedShare;

use App\Controller\Dto\AuthenticationDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\Enums\AuditAction;
use App\Entity\Password;
use App\Entity\TimeBasedShare;
use App\Entity\User;
use App\Repository\PasswordRepository;
use App\Repository\TimeBasedShareRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Audit\AuditService;
use App\Service\Encryption\EncryptionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class GetSensitiveDataController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Get encrypted sensitive data for a time-based share.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptionService  $encryptionService
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  AuthenticationDataDto  $authData
     * @param  AuditService  $auditService
     * @param  WebAuthnCredentialRepository  $webAuthnCredentialRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/time-based-shares/{id}/sensitive-data",
        name: "api_time_based_shares_sensitive_data",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
        #[MapRequestPayload] AuthenticationDataDto $authData,
        AuditService $auditService,
        WebAuthnCredentialRepository $webAuthnCredentialRepository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->encryptionService = $encryptionService;
        $this->passwordHasher = $passwordHasher;
        $this->webAuthnCredentialRepository = $webAuthnCredentialRepository;

        /** @var TimeBasedShareRepository $repository */
        $repository = $entityManager->getRepository(TimeBasedShare::class);
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);

        $share = $repository->findActiveById($id);

        if (is_null($share) || $share->getSharedWith()->getId() !== $loggedInUser->getId()) {
            throw $this->createNotFoundException("Time-based share not found.");
        }

        // Authenticate to track the auth method used (password vs PRF)
        try {
            $this->decryptUserPrivateKeyFromAuth($authData);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Authentication Error',
                'message' => $e->getMessage(),
            ], 401);
        }

        // Track first access
        if (is_null($share->getAccessedAt())) {
            $share->setAccessedAt(new DateTimeImmutable());
            $entityManager->flush();
        }

        // Audit log: Read on the TimeBasedShare
        $auditService->log(AuditAction::Read, $share);

        // Audit log: Read on the source Password (if it still exists)
        $sourcePassword = $passwordRepository->findByIds(
            [$share->getSourcePasswordId()],
            ["PARTIAL p.{id, title, externalId}"]
        )[$share->getSourcePasswordId()] ?? null;

        if (!is_null($sourcePassword)) {
            $auditService->log(AuditAction::Read, $sourcePassword);
        }

        // Build response with encrypted data
        $usernameData = null;
        if (!is_null($share->getEncryptedUsername())) {
            $usernameData = [
                'encryptedData' => $share->getEncryptedUsername(),
                'nonce' => $share->getUsernameNonce(),
                'encryptionPublicKey' => $share->getUsernameEncryptionPublicKey(),
            ];
        }

        return $this->json([
            'password' => [
                'encryptedData' => $share->getEncryptedPassword(),
                'nonce' => $share->getPasswordNonce(),
                'encryptionPublicKey' => $share->getPasswordEncryptionPublicKey(),
            ],
            'username' => $usernameData,
            'userKeys' => $this->buildUserKeysResponse($loggedInUser),
        ]);
    }
}
