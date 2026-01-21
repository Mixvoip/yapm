<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Normalizer;

use App\Entity\Folder;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class FolderNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string MINIMISED = "folder minimised";
    public const string WITH_PARENT = "folder with parent";
    public const string WITH_VAULT = "folder with vault";
    public const string WITH_GROUPS = "folder with groups";
    public const string FOR_SEARCH = "folder for search";

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
        if (in_array(self::FOR_SEARCH, $context, true)) {
            return $this->normalizeForSearch($data);
        }

        $normalised = [
            'id' => $data->getId(),
            'name' => $data->getName(),
            'iconName' => $data->getIconName(),
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
            $groups = [];
            $userShares = [];

            foreach ($data->getFolderGroups() as $folderGroup) {
                $group = $folderGroup->getGroup();

                if ($group->isPrivate()) {
                    // This is a user share - get the user from the group
                    $groupUser = $group->getGroupUsers()->first();
                    if ($groupUser) {
                        $user = $groupUser->getUser();
                        $userShares[] = [
                            'id' => $user->getId(),
                            'email' => $user->getEmail(),
                            'username' => $user->getUsername(),
                            'canWrite' => $folderGroup->canWrite(),
                            'partial' => $folderGroup->isPartial(),
                        ];
                    }
                } else {
                    // Regular group
                    $groups[] = [
                        'id' => $group->getId(),
                        'name' => $group->getName(),
                        'canWrite' => $folderGroup->canWrite(),
                        'partial' => $folderGroup->isPartial(),
                    ];
                }
            }

            $normalised['groups'] = $groups;
            $normalised['users'] = $userShares;
        }

        if (in_array(self::MINIMISED, $context)) {
            return $normalised;
        }

        $normalised['externalId'] = $data->getExternalId();
        $normalised['description'] = $data->getDescription();

        return array_merge($normalised, $this->normalizeMetadata($data));
    }

    /**
     * Normalise a folder for search response.
     *
     * @param  Folder  $folder
     *
     * @return array
     * @throws ExceptionInterface
     */
    #[ArrayShape([
        'id' => "string",
        'name' => "string",
        'iconName' => "string",
        'externalId' => "null|string",
        'vault' => "mixed",
        'folder' => "mixed",
    ])]
    private function normalizeForSearch(Folder $folder): array
    {
        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'iconName' => $folder->getIconName(),
            'externalId' => $folder->getExternalId(),
            'vault' => $this->normalizer->normalize($folder->getVault(), context: [VaultNormalizer::MINIMISED]),
            'folder' => $this->normalizer->normalize($folder->getParent(), context: [FolderNormalizer::MINIMISED]),
        ];
    }
}
