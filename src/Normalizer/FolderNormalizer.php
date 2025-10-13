<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Normalizer;

use App\Entity\Folder;
use App\Entity\FoldersGroup;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class FolderNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string MINIMISED = "folder minimised";
    public const string WITH_PARENT = "folder with parent";
    public const string WITH_VAULT = "folder with vault";
    public const string WITH_GROUPS = "folder with groups";

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Folder;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([Folder::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            Folder::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  Folder  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalised = [
            'id' => $data->getId(),
            'name' => $data->getName(),
        ];

        if (in_array(self::WITH_PARENT, $context)) {
            $normalised['parent'] = $this->normalizer->normalize($data->getParent(), context: $context);
        }

        if (in_array(self::WITH_VAULT, $context)) {
            $normalised['vault'] = $this->normalizer->normalize(
                $data->getVault(),
                context: [VaultNormalizer::MINIMISED]
            );
        }

        if (in_array(self::WITH_GROUPS, $context)) {
            $normalised['groups'] = array_values(
                array_map(
                    function (FoldersGroup $folderGroup) {
                        $group = $folderGroup->getGroup();

                        return [
                            'id' => $group->getId(),
                            'name' => $group->getName(),
                            'canWrite' => $folderGroup->canWrite(),
                            'partial' => $folderGroup->isPartial(),
                        ];
                    },
                    $data->getFolderGroups()->toArray(),
                )
            );
        }

        if (in_array(self::MINIMISED, $context)) {
            return $normalised;
        }

        $normalised['externalId'] = $data->getExternalId();

        return array_merge($normalised, $this->normalizeMetadata($data));
    }
}
