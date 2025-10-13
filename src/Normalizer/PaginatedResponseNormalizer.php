<?php

/**
 * @author bsteffan
 * @since 2025-07-23
 */

namespace App\Normalizer;

use App\Controller\PaginatedResponse;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaginatedResponseNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaginatedResponse;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([PaginatedResponse::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            PaginatedResponse::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  PaginatedResponse  $data
     */
    #[ArrayShape([
        'data' => "mixed",
        'pagination' => "mixed",
    ])]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'data' => $this->normalizer->normalize($data->getData(), $format, $context),
            'pagination' => $this->normalizer->normalize($data->getPagination(), $format, $context),
        ];
    }
}
