<?php

/**
 * @author bsteffan
 * @since 2025-06-26
 */

namespace App\Service\Encryption;

use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;
use RuntimeException;
use SodiumException;

/**
 * Encryption service using libsodium for secure password management
 *
 * Architecture:
 * - Server keypair: ONLY for client-server transport (login, registration, API calls)
 * - User keypairs: For user-specific data encryption (user's private data)
 * - Group keypairs: For group-shared data encryption
 *
 * This separation allows:
 * - Easy server keypair rotation without affecting stored data
 * - Better security isolation
 * - Cleaner key management
 */
class EncryptionService
{
    private const int ARGON2_MEMORY_LIMIT = 64 * 1024 * 1024; // 64MB
    private const int ARGON2_TIME_LIMIT = 3; // 3 iterations

    /**
     * @param  string  $serverPrivateKey
     * @param  string  $serverPublicKey
     */
    public function __construct(
        private readonly string $serverPrivateKey,
        private readonly string $serverPublicKey
    ) {
        // Validate server keys on construction
        if (!$this->isValidPrivateKey($serverPrivateKey) || !$this->isValidPublicKey($serverPublicKey)) {
            throw new InvalidArgumentException('Invalid server keypair provided');
        }
    }

    /**
     * Generate server keypair for client-server transport encryption
     *
     * @return array
     */
    #[ArrayShape([
        'privateKey' => "string",
        'publicKey' => "string",
    ])]
    public static function generateServerKeypair(): array
    {
        try {
            $keypair = sodium_crypto_box_keypair();
            $publicKey = sodium_crypto_box_publickey($keypair);
            $privateKey = sodium_crypto_box_secretkey($keypair);

            sodium_memzero($keypair);

            return [
                'privateKey' => base64_encode($privateKey),
                'publicKey' => base64_encode($publicKey),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to generate server keypair: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt data sent from client using server private key
     * Used for secure client-server communication (registration, password creation, etc.)
     *
     * @param  string  $encryptedData
     * @param  string  $clientPublicKey
     * @param  string  $nonce
     *
     * @return string
     */
    public function decryptFromClient(string $encryptedData, string $clientPublicKey, string $nonce): string
    {
        try {
            $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                base64_decode($this->serverPrivateKey),
                base64_decode($clientPublicKey)
            );

            $decrypted = sodium_crypto_box_open(
                base64_decode($encryptedData),
                base64_decode($nonce),
                $keypair
            );

            if ($decrypted === false) {
                throw new RuntimeException('Failed to decrypt data from client');
            }

            return $decrypted;
        } catch (SodiumException $e) {
            throw new RuntimeException('Decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt data to send to the server(for test purposes)
     *
     * @param  string  $data
     *
     * @return array
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'nonce' => "string",
        'clientPublicKey' => "string",
    ])]
    public function encryptForServer(string $data): array
    {
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
            $keypair = sodium_crypto_box_keypair();
            $tempPublicKey = sodium_crypto_box_publickey($keypair);
            $tempPrivateKey = sodium_crypto_box_secretkey($keypair);

            $this->secureMemzero($keypair);

            $encryptionKeypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $tempPrivateKey,
                base64_decode($this->serverPublicKey)
            );

            $encrypted = sodium_crypto_box(
                $data,
                $nonce,
                $encryptionKeypair
            );

            $this->secureMemzero($tempPrivateKey);
            $this->secureMemzero($encryptionKeypair);

            return [
                'encryptedData' => base64_encode($encrypted),
                'nonce' => base64_encode($nonce),
                'clientPublicKey' => base64_encode($tempPublicKey),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Encryption for client failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate user keypair and encrypt private key with password-derived key
     *
     * @param  string  $password
     *
     * @return array
     * @throws RandomException
     */
    #[ArrayShape([
        'publicKey' => "string",
        'encryptedPrivateKey' => "string",
        'privateKeyNonce' => "string",
        'keySalt' => "string",
    ])]
    public function generateUserKeypair(string $password): array
    {
        try {
            // Generate random salt for key derivation
            $keySalt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

            // Derive key from password using Argon2ID
            $derivedKey = $this->deriveKeyFromPassword($password, $keySalt);

            // Generate user keypair
            $keypair = sodium_crypto_box_keypair();
            $publicKey = sodium_crypto_box_publickey($keypair);
            $privateKey = sodium_crypto_box_secretkey($keypair);

            // Encrypt private key with derived key
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encryptedPrivateKey = sodium_crypto_secretbox($privateKey, $nonce, $derivedKey);

            // Clear sensitive data
            sodium_memzero($derivedKey);
            sodium_memzero($privateKey);

            return [
                'publicKey' => base64_encode($publicKey),
                'encryptedPrivateKey' => base64_encode($encryptedPrivateKey),
                'privateKeyNonce' => base64_encode($nonce),
                'keySalt' => base64_encode($keySalt),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to generate user keypair: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt user private key using password
     *
     * @param  string  $password
     * @param  string  $encryptedPrivateKey
     * @param  string  $nonce
     * @param  string  $keySalt
     *
     * @return string
     */
    public function decryptUserPrivateKey(
        string $password,
        string $encryptedPrivateKey,
        string $nonce,
        string $keySalt
    ): string {
        try {
            $derivedKey = $this->deriveKeyFromPassword($password, base64_decode($keySalt));

            $privateKey = sodium_crypto_secretbox_open(
                base64_decode($encryptedPrivateKey),
                base64_decode($nonce),
                $derivedKey
            );

            sodium_memzero($derivedKey);

            if ($privateKey === false) {
                throw new RuntimeException('Failed to decrypt user private key - invalid password');
            }

            return base64_encode($privateKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to decrypt user private key: ' . $e->getMessage());
        }
    }

    /**
     * Generate group keypair
     *
     * @return array
     */
    #[ArrayShape([
        'publicKey' => "string",
        'privateKey' => "string",
    ])]
    public function generateGroupKeypair(): array
    {
        try {
            $keypair = sodium_crypto_box_keypair();
            $publicKey = sodium_crypto_box_publickey($keypair);
            $privateKey = sodium_crypto_box_secretkey($keypair);

            sodium_memzero($keypair);

            return [
                'publicKey' => base64_encode($publicKey),
                'privateKey' => base64_encode($privateKey),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to generate group keypair: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt group private key for a user
     *
     * @param  string  $groupPrivateKey
     * @param  string  $userPublicKey
     *
     * @return array
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedGroupPrivateKey' => "string",
        'groupPrivateKeyNonce' => "string",
        'encryptionPublicKey' => "string",
    ])]
    public function encryptGroupPrivateKeyForUser(
        string $groupPrivateKey,
        string $userPublicKey
    ): array {
        try {
            // Generate a temporary keypair for this encryption operation
            $tempKeypair = sodium_crypto_box_keypair();
            $tempPrivateKey = sodium_crypto_box_secretkey($tempKeypair);
            $tempPublicKey = sodium_crypto_box_publickey($tempKeypair);

            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $encryptionKeypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $tempPrivateKey,
                base64_decode($userPublicKey)
            );

            $encryptedKey = sodium_crypto_box(
                base64_decode($groupPrivateKey),
                $nonce,
                $encryptionKeypair
            );

            sodium_memzero($tempPrivateKey);

            return [
                'encryptedGroupPrivateKey' => base64_encode($encryptedKey),
                'groupPrivateKeyNonce' => base64_encode($nonce),
                'encryptionPublicKey' => base64_encode($tempPublicKey),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to encrypt group private key for user: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt group private key using user's private key (server-side)
     *
     * @param  string  $encryptedGroupPrivateKey
     * @param  string  $nonce
     * @param  string  $encryptionPublicKey
     * @param  string  $userPrivateKey
     *
     * @return string
     */
    public function decryptGroupPrivateKey(
        string $encryptedGroupPrivateKey,
        string $nonce,
        string $encryptionPublicKey,
        string $userPrivateKey
    ): string {
        try {
            $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                base64_decode($userPrivateKey),
                base64_decode($encryptionPublicKey)
            );

            $decryptedKey = sodium_crypto_box_open(
                base64_decode($encryptedGroupPrivateKey),
                base64_decode($nonce),
                $keypair
            );

            if ($decryptedKey === false) {
                throw new RuntimeException('Failed to decrypt group private key');
            }

            return base64_encode($decryptedKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Group private key decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate random key for password encryption
     *
     * @return string
     */
    public function generatePasswordKey(): string
    {
        try {
            return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        } catch (Exception $e) {
            throw new RuntimeException('Failed to generate password key: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt password data with random key
     *
     * @param  string  $data
     * @param  string  $key
     *
     * @return array
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'nonce' => "string",
    ])]
    public function encryptPasswordData(string $data, string $key): array
    {
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox(
                $data,
                $nonce,
                base64_decode($key)
            );

            return [
                'encryptedData' => base64_encode($encrypted),
                'nonce' => base64_encode($nonce),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to encrypt password data: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt password data using password key (server-side decryption)
     *
     * @param  string  $encryptedData
     * @param  string  $nonce
     * @param  string  $passwordKey
     *
     * @return string
     */
    public function decryptPasswordData(string $encryptedData, string $nonce, string $passwordKey): string
    {
        try {
            $decrypted = sodium_crypto_secretbox_open(
                base64_decode($encryptedData),
                base64_decode($nonce),
                base64_decode($passwordKey)
            );

            if ($decrypted === false) {
                throw new RuntimeException('Failed to decrypt password data');
            }

            return $decrypted;
        } catch (SodiumException $e) {
            throw new RuntimeException('Password data decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt password key for a group
     *
     * @param  string  $passwordKey
     * @param  string  $groupPublicKey
     *
     * @return array
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedPasswordKey' => "string",
        'nonce' => "string",
        'encryptionPublicKey' => "string",
    ])]
    public function encryptPasswordKeyForGroup(
        string $passwordKey,
        string $groupPublicKey
    ): array {
        try {
            // Generate a temporary keypair for this encryption operation
            $tempKeypair = sodium_crypto_box_keypair();
            $tempPrivateKey = sodium_crypto_box_secretkey($tempKeypair);
            $tempPublicKey = sodium_crypto_box_publickey($tempKeypair);

            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $encryptionKeypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $tempPrivateKey,
                base64_decode($groupPublicKey)
            );

            $encryptedKey = sodium_crypto_box(
                base64_decode($passwordKey),
                $nonce,
                $encryptionKeypair
            );

            sodium_memzero($tempPrivateKey);

            return [
                'encryptedPasswordKey' => base64_encode($encryptedKey),
                'nonce' => base64_encode($nonce),
                'encryptionPublicKey' => base64_encode($tempPublicKey),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to encrypt password key for group: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt password key using group private key (server-side)
     *
     * @param  string  $encryptedPasswordKey
     * @param  string  $nonce
     * @param  string  $encryptionPublicKey
     * @param  string  $groupPrivateKey
     *
     * @return string
     */
    public function decryptPasswordKey(
        string $encryptedPasswordKey,
        string $nonce,
        string $encryptionPublicKey,
        string $groupPrivateKey
    ): string {
        try {
            $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                base64_decode($groupPrivateKey),
                base64_decode($encryptionPublicKey)
            );

            $decryptedKey = sodium_crypto_box_open(
                base64_decode($encryptedPasswordKey),
                base64_decode($nonce),
                $keypair
            );

            if ($decryptedKey === false) {
                throw new RuntimeException('Failed to decrypt password key');
            }

            return base64_encode($decryptedKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Password key decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Derive key from password using Argon2ID
     *
     * @param  string  $password
     * @param  string  $salt
     *
     * @return string
     */
    private function deriveKeyFromPassword(string $password, string $salt): string
    {
        try {
            return sodium_crypto_pwhash(
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                $password,
                $salt,
                self::ARGON2_TIME_LIMIT,
                self::ARGON2_MEMORY_LIMIT,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to derive key from password: ' . $e->getMessage());
        }
    }

    /**
     * Validate if string is a valid private key
     *
     * @param  string  $key
     *
     * @return bool
     */
    private function isValidPrivateKey(string $key): bool
    {
        $decoded = base64_decode($key, true);
        return $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_BOX_SECRETKEYBYTES;
    }

    /**
     * Validate if string is a valid public key
     *
     * @param  string  $key
     *
     * @return bool
     */
    private function isValidPublicKey(string $key): bool
    {
        $decoded = base64_decode($key, true);
        return $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_BOX_PUBLICKEYBYTES;
    }

    /**
     * Encrypt decrypted password data for user using user's public key
     *
     * @param  string  $data
     * @param  string  $userPublicKey
     *
     * @return array
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'nonce' => "string",
        'encryptionPublicKey' => "string",
    ])]
    public function encryptForUser(string $data, string $userPublicKey): array
    {
        try {
            // Generate a temporary keypair for this encryption operation
            $tempKeypair = sodium_crypto_box_keypair();
            $tempPrivateKey = sodium_crypto_box_secretkey($tempKeypair);
            $tempPublicKey = sodium_crypto_box_publickey($tempKeypair);

            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $tempPrivateKey,
                base64_decode($userPublicKey)
            );

            $encrypted = sodium_crypto_box(
                $data,
                $nonce,
                $keypair
            );

            sodium_memzero($tempPrivateKey);

            return [
                'encryptedData' => base64_encode($encrypted),
                'nonce' => base64_encode($nonce),
                'encryptionPublicKey' => base64_encode($tempPublicKey),
            ];
        } catch (SodiumException $e) {
            throw new RuntimeException('Encryption for user failed: ' . $e->getMessage());
        }
    }

    /**
     * Safely clear sensitive data from memory
     *
     * @param  string|null  $data
     *
     * @return void
     */
    public function secureMemzero(?string &$data): void
    {
        if ($data === null) {
            return; // Already cleared or never set
        }

        try {
            sodium_memzero($data);
        } catch (SodiumException $e) {
            // Log the memory clearing failure but don't throw
            // This prevents masking the original exception
            error_log("WARNING: Failed to securely clear memory: " . $e->getMessage());

            // Fallback: overwrite with null bytes (not as secure, but better than nothing)
            $data = str_repeat("\0", strlen($data));
        } finally {
            $data = null; // Always nullify the reference
        }
    }
}
