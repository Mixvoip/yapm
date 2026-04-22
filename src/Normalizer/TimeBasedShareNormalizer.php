<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Normalizer;

use App\Domain\AppConstants;
use App\Entity\TimeBasedShare;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TimeBasedShareNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof TimeBasedShare;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([TimeBasedShare::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            TimeBasedShare::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  TimeBasedShare  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $dateContext = [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT];

        return [
            'id' => $data->getId(),
            'passwordTitle' => $data->getPasswordTitle(),
            'sharedBy' => $this->normalizer->normalize(
                $data->getSharedBy(),
                context: [UserNormalizer::MINIMISED]
            ),
            'sharedWith' => $this->normalizer->normalize(
                $data->getSharedWith(),
                context: [UserNormalizer::MINIMISED]
            ),
            'expiresAt' => $this->normalizer->normalize($data->getExpiresAt(), context: $dateContext),
            'accessedAt' => $this->normalizer->normalize($data->getAccessedAt(), context: $dateContext),
            'createdAt' => $this->normalizer->normalize($data->getCreatedAt(), context: $dateContext),
        ];
    }
}
