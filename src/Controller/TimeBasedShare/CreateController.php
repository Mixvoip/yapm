<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Controller\TimeBasedShare;

use App\Controller\EncryptionAwareTrait;
use App\Controller\TimeBasedShare\Dto\CreateTimeBasedShareDto;
use App\Entity\Password;
use App\Entity\TimeBasedShare;
use App\Entity\User;
use App\Repository\PasswordRepository;
use App\Repository\TimeBasedShareRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Encryption\EncryptionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Create a time-based share for a password.
     *
     * @param  CreateTimeBasedShareDto  $dto
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptionService  $encryptionService
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  WebAuthnCredentialRepository  $webAuthnCredentialRepository
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route(
        "/time-based-shares",
        name: "api_time_based_shares_create",
        methods: ["POST"]
    )]
    public function index(
        #[MapRequestPayload] CreateTimeBasedShareDto $dto,
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
        WebAuthnCredentialRepository $webAuthnCredentialRepository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;
        $this->webAuthnCredentialRepository = $webAuthnCredentialRepository;

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        /** @var TimeBasedShareRepository $timeBasedShareRepository */
        $timeBasedShareRepository = $entityManager->getRepository(TimeBasedShare::class);

        // Cannot share with yourself
        if ($dto->recipientId === $loggedInUser->getId()) {
            return $this->json([
                'error' => 'Validation Error',
                'message' => 'You cannot create a time-based share with yourself.',
            ], 400);
        }

        // Load the password with group permissions
        $password = $passwordRepository->findByIds(
            [$dto->passwordId],
            [
                "PARTIAL p.{id, encryptedUsername, title, encryptedPassword, usernameNonce, passwordNonce}",
                "PARTIAL gp.{group, password, encryptedPasswordKey, encryptionPublicKey, nonce}",
                "PARTIAL g.{id, name}",
            ],
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$dto->passwordId] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: {$dto->passwordId} not found.");
        }

        // Load recipient user
        $recipient = $userRepository->findByIds(
            [$dto->recipientId],
            ["PARTIAL u.{id, email, username, publicKey, active}"]
        )[$dto->recipientId] ?? null;

        if (is_null($recipient) || !$recipient->isActive()) {
            return $this->json([
                'error' => 'Validation Error',
                'message' => 'Recipient user not found or is inactive.',
            ], 400);
        }

        if (is_null($recipient->getPublicKey())) {
            return $this->json([
                'error' => 'Validation Error',
                'message' => 'Recipient user has not completed setup.',
            ], 400);
        }

        // Check for existing active share for same password + recipient
        $existingShare = $timeBasedShareRepository->findActiveByPasswordAndRecipient(
            $dto->passwordId,
            $dto->recipientId
        );

        if (!is_null($existingShare)) {
            return $this->json([
                'error' => 'Conflict',
                'message' => 'An active share for this password already exists for this recipient.',
            ], 409);
        }

        // Decrypt sharer's master password and the password data
        $decryptionData = $this->findDecryptionData($password);
        if (is_null($decryptionData)) {
            return $this->json([
                'error' => 'Decryption Error',
                'message' => 'No valid decryption key found for this password.',
            ], 500);
        }

        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKeyFromAuth($dto->authData);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Authentication Error',
                'message' => $e->getMessage(),
            ], 401);
        }

        $decryptedPasswordKey = $this->decryptPasswordKey(
            $decryptionData['groupUser'],
            $decryptionData['groupPassword'],
            $decryptedPrivateKey
        );

        $encryptionService->secureMemzero($decryptedPrivateKey);

        // Decrypt the password data
        $decryptedPassword = $encryptionService->decryptPasswordData(
            $password->getEncryptedPassword(),
            $password->getPasswordNonce(),
            $decryptedPasswordKey
        );

        $decryptedUsername = null;
        if (!is_null($password->getEncryptedUsername())) {
            $decryptedUsername = $encryptionService->decryptPasswordData(
                $password->getEncryptedUsername(),
                $password->getUsernameNonce(),
                $decryptedPasswordKey
            );
        }

        $encryptionService->secureMemzero($decryptedPasswordKey);

        // Re-encrypt for recipient
        $reEncryptedPassword = $encryptionService->encryptForUser(
            $decryptedPassword,
            $recipient->getPublicKey()
        );

        $encryptionService->secureMemzero($decryptedPassword);

        $reEncryptedUsername = null;
        if (!is_null($decryptedUsername)) {
            $reEncryptedUsername = $encryptionService->encryptForUser(
                $decryptedUsername,
                $recipient->getPublicKey()
            );

            $encryptionService->secureMemzero($decryptedUsername);
        }

        // Calculate expiry
        $expiresAt = (new DateTimeImmutable())->add($dto->duration->toDateInterval());

        // Create the share entity
        $share = new TimeBasedShare();
        $share->setSharedBy($loggedInUser)
              ->setSharedWith($recipient)
              ->setPasswordTitle($password->getTitle())
              ->setSourcePasswordId($password->getId())
              ->setEncryptedPassword($reEncryptedPassword['encryptedData'])
              ->setPasswordNonce($reEncryptedPassword['nonce'])
              ->setPasswordEncryptionPublicKey($reEncryptedPassword['encryptionPublicKey'])
              ->setExpiresAt($expiresAt);

        if (!is_null($reEncryptedUsername)) {
            $share->setEncryptedUsername($reEncryptedUsername['encryptedData'])
                  ->setUsernameNonce($reEncryptedUsername['nonce'])
                  ->setUsernameEncryptionPublicKey($reEncryptedUsername['encryptionPublicKey']);
        }

        $entityManager->persist($share);
        $entityManager->flush();

        return $this->json($share, 201);
    }
}
