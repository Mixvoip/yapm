<?php

/**
 * @author bsteffan
 * @since 2025-09-01
 */

namespace App\Normalizer;

use App\Domain\AppConstants;
use App\Entity\ShareProcess;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ShareProcessNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const string MINIMISED = 'share process minimised';

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ShareProcess;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([ShareProcess::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            ShareProcess::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  ShareProcess  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalised = [
            'id' => $data->getId(),
            'scopeId' => $data->getScopeId(),
            'metadata' => $data->getMetadata(),
            'targetType' => $data->getTargetType()->value,
            'cascade' => $data->isCascade(),
            'requestedGroups' => $data->getRequestedGroups(),
            'requestedUsers' => $data->getRequestedUsers(),
            'status' => $data->getStatus()->value,
        ];

        if (in_array(self::MINIMISED, $context, true)) {
            return $normalised;
        }

        $normalised['totalItems'] = $data->getTotalItems();
        $normalised['processedItems'] = $data->getProcessedItems();
        $normalised['failedItems'] = $data->getFailedItems();
        $normalised['message'] = $data->getMessage();
        $normalised['createdAt'] = $this->normalizer->normalize(
            $data->getCreatedAt(),
            context: [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
        );
        $normalised['createdBy'] = $data->getCreatedBy();
        $normalised['updatedAt'] = $this->normalizer->normalize(
            $data->getUpdatedAt(),
            context: [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
        );
        $normalised['startedAt'] = $this->normalizer->normalize(
            $data->getStartedAt(),
            context: [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
        );
        $normalised['finishedAt'] = $this->normalizer->normalize(
            $data->getFinishedAt(),
            context: [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
        );

        return $normalised;
    }
}
