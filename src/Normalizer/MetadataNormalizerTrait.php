<?php

/**
 * @author bsteffan
 * @since 2025-06-24
 */

namespace App\Normalizer;

use App\Domain\AppConstants;
use App\Entity\BaseEntity;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

trait MetadataNormalizerTrait
{
    use NormalizerAwareTrait;

    /**
     * Normalize the created and updated metadata of an entity extending BaseEntity.
     *
     * @param  BaseEntity  $data
     *
     * @return array
     * @throws ExceptionInterface
     */
    #[ArrayShape([
        'createdAt' => "string",
        'createdBy' => "null|string",
        'updatedAt' => "string",
        'updatedBy' => "null|string",
    ])]
    protected function normalizeMetadata(BaseEntity $data): array
    {
        return [
            'createdAt' => $this->normalizer->normalize(
                $data->getCreatedAt(),
                null,
                [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
            ),
            'createdBy' => $data->getCreatedBy(),
            'updatedAt' => $this->normalizer->normalize(
                $data->getUpdatedAt(),
                null,
                [DateTimeNormalizer::FORMAT_KEY => AppConstants::DATE_FORMAT]
            ),
            'updatedBy' => $data->getUpdatedBy(),
        ];
    }
}
