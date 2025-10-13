<?php

/**
 * @author bsteffan
 * @since 2025-06-30
 */

namespace App\Controller;

use App\Controller\Dto\EncryptedClientDataDto;
use App\Entity\GroupsPassword;
use App\Entity\GroupsUser;
use App\Entity\Password;
use App\Entity\User;
use App\Service\Encryption\EncryptionService;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Only use this trait in a controller.
 */
trait EncryptionAwareTrait
{
    protected EncryptionService $encryptionService {
        /**
         * @return EncryptionService
         */
        get {
            return $this->encryptionService;
        }

        /**
         * @param  EncryptionService  $value
         *
         * @return void
         */
        set(EncryptionService $value) {
            $this->encryptionService = $value;
        }
    }

    protected UserPasswordHasherInterface $passwordHasher {
        /**
         *
         * @return UserPasswordHasherInterface
         */
        get {
            return $this->passwordHasher;
        }

        /**
         * @param  UserPasswordHasherInterface  $value
         *
         * @return void
         */
        set(UserPasswordHasherInterface $value) {
            $this->passwordHasher = $value;
        }
    }

    /**
     * Check if the passwords match and decrypt the user private key.
     *
     * @param  EncryptedClientDataDto  $encryptedPassword
     *
     * @return string
     * @throws Exception
     */
    protected function decryptUserPrivateKey(EncryptedClientDataDto $encryptedPassword): string
    {
        /** @var User $user */
        $user = $this->getUser();

        $plainTextPassword = $this->encryptionService->decryptFromClient(
            $encryptedPassword->encryptedData,
            $encryptedPassword->clientPublicKey,
            $encryptedPassword->nonce
        );

        if (!$this->passwordHasher->isPasswordValid($user, $plainTextPassword)) {
            $this->encryptionService->secureMemzero($plainTextPassword);
            throw new Exception("Invalid password.");
        }

        $decryptedPrivateKey = $this->encryptionService->decryptUserPrivateKey(
            $plainTextPassword,
            $user->getEncryptedPrivateKey(),
            $user->getPrivateKeyNonce(),
            $user->getKeySalt()
        );

        $this->encryptionService->secureMemzero($plainTextPassword);

        return $decryptedPrivateKey;
    }

    /**
     * Decrypt the password key.
     *
     * @param  GroupsUser  $groupUser
     * @param  GroupsPassword  $groupPassword
     * @param  string  $decryptedPrivateKey
     *
     * @return string
     */
    private function decryptPasswordKey(
        GroupsUser $groupUser,
        GroupsPassword $groupPassword,
        string $decryptedPrivateKey
    ): string {
        $decryptedGroupKey = $this->encryptionService->decryptGroupPrivateKey(
            $groupUser->getEncryptedGroupPrivateKey(),
            $groupUser->getGroupPrivateKeyNonce(),
            $groupUser->getEncryptionPublicKey(),
            $decryptedPrivateKey,
        );

        $decryptedPasswordKey = $this->encryptionService->decryptPasswordKey(
            $groupPassword->getEncryptedPasswordKey(),
            $groupPassword->getNonce(),
            $groupPassword->getEncryptionPublicKey(),
            $decryptedGroupKey,
        );

        $this->encryptionService->secureMemzero($decryptedGroupKey);

        return $decryptedPasswordKey;
    }

    /**
     * Find the necessary data for decryption.
     *
     * @param  Password  $password
     *
     * @return array|null
     */
    private function findDecryptionData(Password $password): ?array
    {
        /** @var User $user */
        $user = $this->getUser();

        foreach ($password->getGroupPasswords() as $groupPassword) {
            $groupId = $groupPassword->getGroup()->getId();

            if (in_array($groupId, $user->getGroupIds())) {
                $groupUser = $user->getGroupUserForGroup($groupId);

                if (!is_null($groupUser)) {
                    return [
                        'groupPassword' => $groupPassword,
                        'groupUser' => $groupUser,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Decrypt client data and encrypt it with the password key.
     *
     * @param  EncryptedClientDataDto|null  $clientEncryptedData
     * @param  string  $passwordKey
     *
     * @return array|null
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'encryptedDataNonce' => "string",
    ])]
    private function encryptPasswordData(?EncryptedClientDataDto $clientEncryptedData, string $passwordKey): ?array
    {
        if (is_null($clientEncryptedData)) {
            return null;
        }

        $decryptedClientData = $this->encryptionService->decryptFromClient(
            $clientEncryptedData->encryptedData,
            $clientEncryptedData->clientPublicKey,
            $clientEncryptedData->nonce
        );

        $encryptedPasswordData = $this->encryptionService->encryptPasswordData($decryptedClientData, $passwordKey);
        $this->encryptionService->secureMemzero($decryptedClientData);

        return [
            'encryptedData' => $encryptedPasswordData['encryptedData'],
            'encryptedDataNonce' => $encryptedPasswordData['nonce'],
        ];
    }
}
