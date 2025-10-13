<?php

/**
 * @author bsteffan
 * @since 2025-07-16
 */

namespace App\Normalizer;

use App\Domain\AppConstants;
use App\Entity\AuditLog;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AuditLogNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const string CHANGELOG = 'audit log changelog';
    public const string SUMMARY = 'audit log summary';

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AuditLog;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([AuditLog::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            AuditLog::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  AuditLog  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (in_array(self::CHANGELOG, $context, true)) {
            return $this->normalizeForChangelog($data);
        }

        if (in_array(self::SUMMARY, $context, true)) {
            return $this->normalizeForSummary($data);
        }

        return [
            'id' => $data->getId(),
            'actionType' => $data->getActionType()->value,
            'entityType' => $data->getEntityType(),
            'entityId' => $data->getEntityId(),
            'userId' => $data->getUserId(),
            'userEmail' => $data->getUserEmail(),
            'ipAddress' => $data->getIpAddress(),
            'userAgent' => $data->getUserAgent(),
            'oldValues' => $data->getOldValues(),
            'newValues' => $data->getNewValues(),
            'metadata' => $data->getMetadata(),
            'createdAt' => $this->normalizer->normalize(
                $data->getCreatedAt(),
                null,
                [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
            ),
        ];
    }

    /**
     * Normalize the audit log for the changelog view.
     *
     * @param  AuditLog  $data
     *
     * @return array
     * @throws ExceptionInterface
     */
    #[ArrayShape([
        'id' => "string",
        'actionType' => "string",
        'userEmail' => "null|string",
        'oldValues' => "array|null",
        'newValues' => "array|null",
        'createdAt' => "mixed",
    ])]
    private function normalizeForChangelog(AuditLog $data): array
    {
        return [
            'id' => $data->getId(),
            'actionType' => $data->getActionType()->value,
            'userEmail' => $data->getUserEmail(),
            'oldValues' => $data->getOldValues(),
            'newValues' => $data->getNewValues(),
            'createdAt' => $this->normalizer->normalize(
                $data->getCreatedAt(),
                null,
                [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
            ),
        ];
    }

    /**
     * Normalize the audit log for the summary view.
     *
     * @param  AuditLog  $data
     *
     * @return array
     * @throws ExceptionInterface
     */
    #[ArrayShape([
        'type' => "string",
        'userEmail' => "null|string",
        'fields' => "array|null",
        'time' => "mixed",
    ])]
    private function normalizeForSummary(AuditLog $data): array
    {
        $normalized = [
            'id' => $data->getId(),
            'type' => $data->getActionType()->value,
            'userEmail' => $data->getUserEmail(),
            'timeStamp' => $this->normalizer->normalize(
                $data->getCreatedAt(),
                null,
                [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
            ),
        ];

        $normalized['fields'] = [];
        foreach ($data->getNewValues() as $key => $value) {
            $normalized['fields'][] = $key;
        }

        return $normalized;
    }
}
