<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Service\WebAuthn;

use App\Entity\User;
use App\Entity\WebAuthnCredential;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\Utility\Base64UrlHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnService
{
    private readonly SerializerInterface $webauthnSerializer;
    private readonly CeremonyStepManagerFactory $ceremonyStepManagerFactory;
    private readonly AttestationStatementSupportManager $attestationStatementSupportManager;

    /**
     * @param  string  $rpId
     * @param  string  $rpName
     * @param  string  $rpOrigin
     * @param  EntityManagerInterface  $entityManager
     * @param  WebAuthnCredentialRepository  $credentialRepository
     * @param  ChallengeStore  $challengeStore
     */
    public function __construct(
        private readonly string $rpId,
        private readonly string $rpName,
        private readonly string $rpOrigin,
        private readonly EntityManagerInterface $entityManager,
        private readonly WebAuthnCredentialRepository $credentialRepository,
        private readonly ChallengeStore $challengeStore
    ) {
        $this->attestationStatementSupportManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);

        $this->webauthnSerializer = (new WebauthnSerializerFactory(
            $this->attestationStatementSupportManager
        ))->create();

        $this->ceremonyStepManagerFactory = new CeremonyStepManagerFactory();
        $this->ceremonyStepManagerFactory->setAllowedOrigins([$this->rpOrigin]);
    }

    /**
     * Generate registration options for a user.
     *
     * @param  User  $user
     *
     * @return array{options: string, prfSalt: string}
     */
    public function generateRegistrationOptions(User $user): array
    {
        $existingCredentials = $this->credentialRepository->findByUser($user);
        $excludeCredentials = array_map(
            fn(WebAuthnCredential $cred) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $this->deserializeSource($cred->getPublicKeyCredentialSource())->publicKeyCredentialId
            ),
            $existingCredentials
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId),
            user: PublicKeyCredentialUserEntity::create(
                $user->getUsername(),
                $user->getId(),
                $user->getUsername()
            ),
            challenge: random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(-7),   // ES256
                PublicKeyCredentialParameters::createPk(-257), // RS256
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $excludeCredentials,
            timeout: 60000,
        );

        $prfSalt = base64_encode(random_bytes(32));

        $challengeKey = $user->getId() . '_registration';
        $this->challengeStore->store($challengeKey, base64_encode($options->challenge), ['prfSalt' => $prfSalt]);

        return [
            'options' => $this->webauthnSerializer->serialize($options, 'json'),
            'prfSalt' => $prfSalt,
        ];
    }

    /**
     * Verify a registration response from the browser.
     *
     * @param  User  $user
     * @param  string  $responseJson
     * @param  string  $prfSalt  The PRF salt sent by the client, verified against the stored value
     *
     * @return PublicKeyCredentialSource
     */
    public function verifyRegistration(User $user, string $responseJson, string $prfSalt): PublicKeyCredentialSource
    {
        $challengeKey = $user->getId() . '_registration';
        $storedData = $this->challengeStore->retrieve($challengeKey);

        if (is_null($storedData)) {
            throw new RuntimeException('Registration challenge not found or expired');
        }

        // Verify the PRF salt matches the one generated during options
        $storedPrfSalt = $storedData['metadata']['prfSalt'] ?? null;
        if (is_null($storedPrfSalt) || !hash_equals($storedPrfSalt, $prfSalt)) {
            throw new RuntimeException('PRF salt mismatch');
        }

        $credential = $this->webauthnSerializer->deserialize(
            $responseJson,
            PublicKeyCredential::class,
            'json'
        );

        $creationOptions = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId),
            user: PublicKeyCredentialUserEntity::create(
                $user->getUsername(),
                $user->getId(),
                $user->getUsername()
            ),
            challenge: base64_decode($storedData['challenge']),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            timeout: 60000,
        );

        $validator = AuthenticatorAttestationResponseValidator::create(
            $this->ceremonyStepManagerFactory->creationCeremony()
        );

        return $validator->check($credential->response, $creationOptions, $this->rpId);
    }

    /**
     * Generate authentication options for sensitive operations (authenticated user).
     * Includes PRF salt per credential so the client can derive the encryption key.
     *
     * @param  User  $user
     *
     * @return array{options: string, prfSalts: array<string, string>, credentialSettings: array<string, array{cacheOnUse: bool}>}
     */
    public function generateAuthenticationOptions(User $user): array
    {
        $credentials = $this->credentialRepository->findByUser($user);

        if (empty($credentials)) {
            throw new RuntimeException('User has no registered passkeys');
        }

        $allowCredentials = [];
        $prfSalts = [];
        $credentialSettings = [];

        foreach ($credentials as $credential) {
            $source = $this->deserializeSource($credential->getPublicKeyCredentialSource());
            $credentialIdBase64url = Base64UrlHelper::encode($source->publicKeyCredentialId);

            $allowCredentials[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $source->publicKeyCredentialId,
                $source->transports
            );

            $prfSalts[$credentialIdBase64url] = $credential->getPrfSalt();
            $credentialSettings[$credentialIdBase64url] = [
                'cacheOnUse' => $credential->isCacheOnUse(),
            ];
        }

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->rpId,
            allowCredentials: $allowCredentials,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_DISCOURAGED,
            timeout: 60000,
        );

        $challengeKey = $user->getId() . '_authentication';
        $this->challengeStore->store($challengeKey, base64_encode($options->challenge));

        return [
            'options' => $this->webauthnSerializer->serialize($options, 'json'),
            'prfSalts' => $prfSalts,
            'credentialSettings' => $credentialSettings,
        ];
    }

    /**
     * Generate login options for discoverable credential (usernameless) passkey login.
     * No user context needed — the browser shows all resident credentials for the RP.
     *
     * @return array{options: string, sessionToken: string}
     */
    public function generateDiscoverableLoginOptions(): array
    {
        $sessionToken = bin2hex(random_bytes(32));

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->rpId,
            allowCredentials: [],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $challengeKey = 'login_' . $sessionToken;
        $this->challengeStore->store($challengeKey, base64_encode($options->challenge));

        return [
            'options' => $this->webauthnSerializer->serialize($options, 'json'),
            'sessionToken' => $sessionToken,
        ];
    }

    /**
     * Generate login options for unauthenticated passkey login.
     *
     * @param  User  $user
     *
     * @return array{options: string, sessionToken: string, prfSalts: array<string, string>}
     */
    public function generateLoginOptions(User $user): array
    {
        $credentials = $this->credentialRepository->findByUser($user);

        if (empty($credentials)) {
            throw new RuntimeException('User has no registered passkeys');
        }

        $allowCredentials = [];
        $prfSalts = [];

        foreach ($credentials as $credential) {
            $source = $this->deserializeSource($credential->getPublicKeyCredentialSource());
            $credentialIdBase64url = Base64UrlHelper::encode($source->publicKeyCredentialId);

            $allowCredentials[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $source->publicKeyCredentialId,
                $source->transports
            );

            $prfSalts[$credentialIdBase64url] = $credential->getPrfSalt();
        }

        $sessionToken = bin2hex(random_bytes(32));

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->rpId,
            allowCredentials: $allowCredentials,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $challengeKey = 'login_' . $sessionToken;
        $this->challengeStore->store($challengeKey, base64_encode($options->challenge));

        return [
            'options' => $this->webauthnSerializer->serialize($options, 'json'),
            'sessionToken' => $sessionToken,
            'prfSalts' => $prfSalts,
        ];
    }

    /**
     * Verify an assertion for login (unauthenticated - user determined from credential).
     *
     * @param  string  $responseJson
     * @param  string  $sessionToken
     *
     * @return WebAuthnCredential
     */
    public function verifyLoginAssertion(string $responseJson, string $sessionToken): WebAuthnCredential
    {
        $challengeKey = 'login_' . $sessionToken;
        $storedData = $this->challengeStore->retrieve($challengeKey);

        if (is_null($storedData)) {
            throw new RuntimeException('Login challenge not found or expired');
        }

        $credential = $this->webauthnSerializer->deserialize(
            $responseJson,
            PublicKeyCredential::class,
            'json'
        );

        $webAuthnCredential = $this->credentialRepository->findByCredentialId($credential->rawId);

        if (is_null($webAuthnCredential)) {
            throw new RuntimeException('Unknown credential');
        }

        $source = $this->deserializeSource($webAuthnCredential->getPublicKeyCredentialSource());

        $requestOptions = PublicKeyCredentialRequestOptions::create(
            challenge: base64_decode($storedData['challenge']),
            rpId: $this->rpId,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $validator = AuthenticatorAssertionResponseValidator::create(
            $this->ceremonyStepManagerFactory->requestCeremony()
        );

        $user = $webAuthnCredential->getUser();

        $updatedSource = $validator->check(
            $source,
            $credential->response,
            $requestOptions,
            $this->rpId,
            $user->getId()
        );

        // Persist updated source
        $webAuthnCredential->setPublicKeyCredentialSource(
            $this->serializeSource($updatedSource)
        );
        $webAuthnCredential->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $webAuthnCredential;
    }

    /**
     * Serialize a PublicKeyCredentialSource to JSON string for storage.
     *
     * @param  PublicKeyCredentialSource  $source
     *
     * @return string
     */
    public function serializeSource(PublicKeyCredentialSource $source): string
    {
        return $this->webauthnSerializer->serialize($source, 'json');
    }

    /**
     * Deserialize a PublicKeyCredentialSource from JSON string.
     *
     * @param  string  $json
     *
     * @return PublicKeyCredentialSource
     */
    public function deserializeSource(string $json): PublicKeyCredentialSource
    {
        return $this->webauthnSerializer->deserialize($json, PublicKeyCredentialSource::class, 'json');
    }
}
