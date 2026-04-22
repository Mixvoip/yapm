<?php

/**
 * @author bsteffan
 * @since 2025-06-30
 */

namespace App\Controller\Password;

use App\Controller\Dto\AuthenticationDataDto;
use App\Controller\EncryptionAwareTrait;
use App\Entity\Enums\AuditAction;
use App\Entity\GroupsPassword;
use App\Entity\GroupsUser;
use App\Entity\User;
use App\Repository\PasswordRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Audit\AuditService;
use App\Service\Encryption\EncryptionService;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class GetSensitiveDataController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Get sensitive data for a password.
     *
     * @param  string  $id
     * @param  PasswordRepository  $passwordRepository
     * @param  EncryptionService  $encryptionService
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  AuthenticationDataDto  $authData
     * @param  AuditService  $auditService
     * @param  WebAuthnCredentialRepository  $webAuthnCredentialRepository
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route(
        "/passwords/{id}/sensitive",
        name: "api_passwords_id_sensitive",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    public function index(
        string $id,
        PasswordRepository $passwordRepository,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
        #[MapRequestPayload] AuthenticationDataDto $authData,
        AuditService $auditService,
        WebAuthnCredentialRepository $webAuthnCredentialRepository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;
        $this->webAuthnCredentialRepository = $webAuthnCredentialRepository;

        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id, encryptedUsername, title, externalId, target, encryptedPassword, usernameNonce, passwordNonce}",
                "PARTIAL gp.{group, password, encryptedPasswordKey, encryptionPublicKey, nonce}",
                "PARTIAL g.{id, name}",
            ],
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        $decryptionData = $this->findDecryptionData($password);
        if (is_null($decryptionData)) {
            return $this->json([
                'error' => "Decryption Error",
                'message' => "No valid decryption key found for this password.",
            ], 500);
        }

        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKeyFromAuth($authData);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        /** @var GroupsUser $groupUser */
        $groupUser = $decryptionData['groupUser'];
        /** @var GroupsPassword $groupPassword */
        $groupPassword = $decryptionData['groupPassword'];
        $decryptedPasswordKey = $this->decryptPasswordKey($groupUser, $groupPassword, $decryptedPrivateKey);

        $encryptionService->secureMemzero($decryptedPrivateKey);

        $decryptedPassword = $encryptionService->decryptPasswordData(
            $password->getEncryptedPassword(),
            $password->getPasswordNonce(),
            $decryptedPasswordKey
        );

        if (!is_null($password->getEncryptedUsername())) {
            $decryptedUsername = $encryptionService->decryptPasswordData(
                $password->getEncryptedUsername(),
                $password->getUsernameNonce(),
                $decryptedPasswordKey
            );

            $reEncryptedUsername = $encryptionService->encryptForUser(
                $decryptedUsername,
                $loggedInUser->getPublicKey()
            );
        }

        $encryptionService->secureMemzero($decryptedPasswordKey);

        $reEncryptedPassword = $encryptionService->encryptForUser(
            $decryptedPassword,
            $loggedInUser->getPublicKey()
        );

        $auditService->log(AuditAction::Read, $password);

        return $this->json([
            'username' => $reEncryptedUsername ?? null,
            'password' => $reEncryptedPassword,
            'userKeys' => $this->buildUserKeysResponse($loggedInUser),
        ]);
    }
}
