<?php

/**
 * @author bsteffan
 * @since 2025-08-06
 */

namespace App\Controller\Password;

use App\Controller\EncryptionAwareTrait;
use App\Controller\Password\Dto\PatchSensitiveDataDto;
use App\Entity\GroupsPassword;
use App\Entity\GroupsUser;
use App\Entity\Password;
use App\Entity\User;
use App\Repository\PasswordRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PatchSensitiveDataController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Update sensitive data for a password.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  PatchSensitiveDataDto  $dto
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     *
     * @return Response
     * @throws RandomException
     */
    #[Route(
        "/passwords/{id}/sensitive",
        name: "api_patch_passwords_id_sensitive",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        #[MapRequestPayload] PatchSensitiveDataDto $dto,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService
    ): Response {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);
        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id, title, externalId, target, encryptedUsername, encryptedPassword, usernameNonce, passwordNonce, updatedAt, updatedBy}",
                "PARTIAL gp.{group, password, encryptedPasswordKey, encryptionPublicKey, nonce, canWrite}",
                "PARTIAL g.{id, name}",
            ],
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        if (!$password->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to update this password.");
        }

        $decryptionData = $this->findDecryptionData($password);
        if (is_null($decryptionData)) {
            return $this->json([
                'error' => "Decryption Error",
                'message' => "No valid decryption key found for this password.",
            ]);
        }

        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKey($dto->encryptedUserPassword);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        $isUsernameUpdate = $dto->encryptedUsername !== false;
        $isPasswordUpdate = $dto->encryptedPassword !== false;

        if (!$isUsernameUpdate && !$isPasswordUpdate) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if ($isUsernameUpdate && $dto->encryptedUsername === null && !$isPasswordUpdate) {
            $this->encryptionService->secureMemzero($decryptedPrivateKey);

            $password->setEncryptedUsername(null)
                     ->setUsernameNonce(null)
                     ->setUpdatedBy($loggedInUser->getUserIdentifier());

            $entityManager->flush();
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        /** @var GroupsUser $groupUser */
        $groupUser = $decryptionData['groupUser'];
        /** @var GroupsPassword $groupPassword */
        $groupPassword = $decryptionData['groupPassword'];
        $decryptedPasswordKey = $this->decryptPasswordKey($groupUser, $groupPassword, $decryptedPrivateKey);

        $encryptionService->secureMemzero($decryptedPrivateKey);

        if ($isUsernameUpdate && !is_null($dto->encryptedUsername)) {
            $encryptedUsernameData = $this->encryptPasswordData($dto->encryptedUsername, $decryptedPasswordKey);

            $password->setEncryptedUsername($encryptedUsernameData['encryptedData'])
                     ->setUsernameNonce($encryptedUsernameData['encryptedDataNonce']);
        }

        if ($isPasswordUpdate) {
            $encryptedPasswordData = $this->encryptPasswordData($dto->encryptedPassword, $decryptedPasswordKey);
            $password->setEncryptedPassword($encryptedPasswordData['encryptedData'])
                     ->setPasswordNonce($encryptedPasswordData['encryptedDataNonce']);
        }

        $encryptionService->secureMemzero($decryptedPasswordKey);

        $password->setUpdatedBy($loggedInUser->getUserIdentifier());

        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
