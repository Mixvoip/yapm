<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Normalizer;

use App\Domain\AppConstants;
use App\Entity\WebAuthnCredential;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class WebAuthnCredentialNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string LIST_VIEW = 'webauthn_credential list';

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof WebAuthnCredential;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([WebAuthnCredential::class => true])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            WebAuthnCredential::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  WebAuthnCredential  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalised = [
            'id' => $data->getId(),
            'name' => $data->getName(),
            'cacheOnUse' => $data->isCacheOnUse(),
            'lastUsedAt' => !is_null($data->getLastUsedAt())
                ? $this->normalizer->normalize(
                    $data->getLastUsedAt(),
                    null,
                    [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
                )
                : null,
        ];

        return array_merge($normalised, $this->normalizeMetadata($data));
    }
}
