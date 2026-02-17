<?php

/**
 * @author bsteffan
 * @since 2025-08-06
 */

namespace App\Controller\Password;

use App\Controller\AbstractJsonPatchController;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;

class PatchSensitiveDataController extends AbstractJsonPatchController
{
    use EncryptionAwareTrait;

    /**
     * Update sensitive data for a password.
     *
     * @param  string  $id
     * @param  Request  $request
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
        Request $request,
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
                "PARTIAL p.{id, title, externalId, target, encryptedUsername, encryptedPassword, usernameNonce, passwordNonce, encryptedTotpSecretKey, totpSecretKeyNonce, totpAlgorithm, totpPeriod, totpDigits, updatedAt, updatedBy}",
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

        $this->initializePatchData($request);

        $isUsernameUpdate = $this->isPatchRequested('encryptedUsername');
        $isPasswordUpdate = $this->isPatchRequested('encryptedPassword');
        $isTotpUpdate = $this->isPatchRequested('totp');

        // Validate that encryptedPassword cannot be null if requested
        if ($isPasswordUpdate && is_null($dto->encryptedPassword)) {
            $violation = new ConstraintViolation(
                "Password cannot be cleared.",
                null,
                [],
                null,
                "encryptedPassword",
                null
            );
            $this->addViolation($violation);
            $this->throwViolations();
        }

        if (!$isUsernameUpdate && !$isPasswordUpdate && !$isTotpUpdate) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $decryptionData = $this->findDecryptionData($password);
        if (is_null($decryptionData)) {
            return $this->json([
                'error' => "Decryption Error",
                'message' => "No valid decryption key found for this password.",
            ], 500);
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

        // Handle case where only clearing nullable fields (no encryption needed)
        $isClearUsernameOnly = $isUsernameUpdate && is_null($dto->encryptedUsername);
        $isClearTotpOnly = $isTotpUpdate && is_null($dto->totp);
        $needsEncryption = $isPasswordUpdate
                           || ($isUsernameUpdate && !is_null($dto->encryptedUsername))
                           || ($isTotpUpdate && !is_null($dto->totp));

        if (!$needsEncryption) {
            $this->encryptionService->secureMemzero($decryptedPrivateKey);

            if ($isClearUsernameOnly) {
                $password->setEncryptedUsername(null)
                         ->setUsernameNonce(null);
            }

            if ($isClearTotpOnly) {
                $password->clearTotp();
            }

            $password->setUpdatedBy($loggedInUser->getUserIdentifier());

            $entityManager->flush();
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        /** @var GroupsUser $groupUser */
        $groupUser = $decryptionData['groupUser'];
        /** @var GroupsPassword $groupPassword */
        $groupPassword = $decryptionData['groupPassword'];
        $decryptedPasswordKey = $this->decryptPasswordKey($groupUser, $groupPassword, $decryptedPrivateKey);

        $encryptionService->secureMemzero($decryptedPrivateKey);

        if ($isUsernameUpdate) {
            if (is_null($dto->encryptedUsername)) {
                $password->setEncryptedUsername(null)
                         ->setUsernameNonce(null);
            } else {
                $encryptedUsernameData = $this->encryptPasswordData($dto->encryptedUsername, $decryptedPasswordKey);

                $password->setEncryptedUsername($encryptedUsernameData['encryptedData'])
                         ->setUsernameNonce($encryptedUsernameData['encryptedDataNonce']);
            }
        }

        if ($isPasswordUpdate) {
            $encryptedPasswordData = $this->encryptPasswordData($dto->encryptedPassword, $decryptedPasswordKey);
            $password->setEncryptedPassword($encryptedPasswordData['encryptedData'])
                     ->setPasswordNonce($encryptedPasswordData['encryptedDataNonce']);
        }

        if ($isTotpUpdate) {
            if (is_null($dto->totp)) {
                $password->clearTotp();
            } else {
                $encryptedTotpSecretKeyData = $this->encryptPasswordData(
                    $dto->totp->encryptedSecretKey,
                    $decryptedPasswordKey
                );

                $password->setEncryptedTotpSecretKey($encryptedTotpSecretKeyData['encryptedData'])
                         ->setTotpSecretKeyNonce($encryptedTotpSecretKeyData['encryptedDataNonce'])
                         ->setTotpAlgorithm($dto->totp->algorithm)
                         ->setTotpPeriod($dto->totp->period)
                         ->setTotpDigits($dto->totp->digits);
            }
        }

        $encryptionService->secureMemzero($decryptedPasswordKey);

        $password->setUpdatedBy($loggedInUser->getUserIdentifier());

        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
