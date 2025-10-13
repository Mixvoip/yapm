<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Normalizer;

use App\Entity\GroupsVault;
use App\Entity\Vault;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class VaultNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string MINIMISED = "vault minimised";
    public const string WITH_GROUPS = "vault with groups";
    public const string WITH_MANDATORY_FIELDS = "vault with mandatory fields";

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Vault;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([Vault::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            Vault::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  Vault  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalised = [
            'id' => $data->getId(),
            'name' => $data->getName(),
            'private' => $data->isPrivate(),
            'iconName' => $data->getIconName(),
            'allowPasswordsAtRoot' => $data->isAllowPasswordsAtRoot(),
        ];

        if (in_array(self::WITH_GROUPS, $context)) {
            $normalised['groups'] = array_values(
                array_map(
                    function (GroupsVault $groupVault) {
                        $group = $groupVault->getGroup();

                        return [
                            'id' => $group->getId(),
                            'name' => $group->getName(),
                            'canWrite' => $groupVault->canWrite(),
                            'partial' => $groupVault->isPartial(),
                        ];
                    },
                    $data->getGroupVaults()->toArray(),
                )
            );
        }

        if (in_array(self::WITH_MANDATORY_FIELDS, $context)) {
            $normalised['mandatoryPasswordFields'] = null;
            $mandatoryPasswordFields = $data->getMandatoryPasswordFields();
            if (!is_null($mandatoryPasswordFields)) {
                $normalised['mandatoryPasswordFields'] = [];
                foreach ($mandatoryPasswordFields as $mandatoryPasswordField) {
                    $normalised['mandatoryPasswordFields'][] = $mandatoryPasswordField->value;
                }
            }

            $normalised['mandatoryFolderFields'] = null;
            $mandatoryFolderFields = $data->getMandatoryFolderFields();
            if (!is_null($mandatoryFolderFields)) {
                $normalised['mandatoryFolderFields'] = [];
                foreach ($mandatoryFolderFields as $mandatoryFolderField) {
                    $normalised['mandatoryFolderFields'][] = $mandatoryFolderField->value;
                }
            }
        }

        if (in_array(self::MINIMISED, $context)) {
            return $normalised;
        }

        return array_merge($normalised, $this->normalizeMetadata($data));
    }
}
