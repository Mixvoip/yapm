<?php

/**
 * @author bsteffan
 * @since 2025-04-28
 */

namespace App\Normalizer;

use App\Entity\Group;
use App\Entity\GroupsUser;
use App\Entity\User;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class GroupNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string WITH_USERS = 'group with users';
    public const string WITH_USER_COUNT = 'group with user count';
    public const string WITH_MANAGERS = 'group with managers';
    public const string MINIMISED = 'group minimised';

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Group;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([Group::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            Group::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  Group  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalised = [
            "id" => $data->getId(),
            "name" => $data->getName(),
            "private" => $data->isPrivate(),
        ];

        if (in_array(self::WITH_USERS, $context)) {
            $normalised['users'] = array_values(array_map(function (GroupsUser $groupUser) {
                return $this->normalizer->normalize($groupUser->getUser(), context: [UserNormalizer::MINIMISED]);
            }, $data->getGroupUsers()->toArray()));
        }

        if (in_array(self::WITH_USER_COUNT, $context)) {
            $normalised['userCount'] = $data->getGroupUsers()->count();
        }

        if (in_array(self::WITH_MANAGERS, $context)) {
            $normalised['managers'] = array_values(array_map(function (User $user) {
                return $this->normalizer->normalize($user, context: [UserNormalizer::MINIMISED]);
            }, $data->getManagers()));
        }

        if (in_array(self::MINIMISED, $context)) {
            return $normalised;
        }

        return array_merge($normalised, $this->normalizeMetadata($data));
    }
}
