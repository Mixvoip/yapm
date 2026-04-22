<?php

/**
 * @author bsteffan
 * @since 2026-03-12
 */

namespace App\Controller\User;

use App\Controller\EncryptionAwareTrait;
use App\Controller\User\Dto\ChangePasswordDto;
use App\Entity\User;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ChangePasswordController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Change the authenticated user's master password.
     *
     * @param  ChangePasswordDto  $dto
     * @param  EntityManagerInterface  $entityManager
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     * @param  WebAuthnCredentialRepository  $webAuthnCredentialRepository
     *
     * @return Response
     */
    #[Route('/change-password', name: 'api_user_change_password', methods: ['POST'])]
    public function index(
        #[MapRequestPayload] ChangePasswordDto $dto,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService,
        WebAuthnCredentialRepository $webAuthnCredentialRepository
    ): Response {
        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;
        $this->webAuthnCredentialRepository = $webAuthnCredentialRepository;

        /** @var User $user */
        $user = $this->getUser();

        // Verify current password/PRF and get decrypted private key
        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKeyFromAuth($dto->authData);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        // Decrypt new password from transport encryption
        $newPlainPassword = $encryptionService->decryptFromClient(
            $dto->newEncryptedPassword->encryptedData,
            $dto->newEncryptedPassword->clientPublicKey,
            $dto->newEncryptedPassword->nonce
        );

        // Validate new password strength
        if (!preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/", $newPlainPassword)) {
            $encryptionService->secureMemzero($newPlainPassword);
            $encryptionService->secureMemzero($decryptedPrivateKey);

            throw new BadRequestHttpException(
                "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character."
            );
        }

        // Re-encrypt user's private key with new password
        $reEncryptedKey = $encryptionService->reEncryptUserPrivateKey($decryptedPrivateKey, $newPlainPassword);

        // Update user entity
        $user->setPassword($passwordHasher->hashPassword($user, $newPlainPassword))
             ->setKeySalt($reEncryptedKey['keySalt'])
             ->setEncryptedPrivateKey($reEncryptedKey['encryptedPrivateKey'])
             ->setPrivateKeyNonce($reEncryptedKey['privateKeyNonce'])
             ->setUpdatedBy($user->getUserIdentifier());

        // Clear sensitive data from memory
        $encryptionService->secureMemzero($newPlainPassword);
        $encryptionService->secureMemzero($decryptedPrivateKey);

        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
