<?php

namespace App\Normalizer;

use App\Entity\GroupsUser;
use App\Entity\User;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UserNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string MINIMISED = 'user minimised';
    public const string WITH_GROUPS = 'user with groups';

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([User::class => true])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  User  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalised = [
            "id" => $data->getId(),
            "email" => $data->getEmail(),
            "username" => $data->getUsername(),
        ];

        if (in_array(self::WITH_GROUPS, $context)) {
            $normalised['groups'] = array_values(array_map(function (GroupsUser $groupUser) {
                return $this->normalizer->normalize($groupUser->getGroup(), context: [GroupNormalizer::MINIMISED]);
            }, $data->getGroupUsers()->toArray()));
        }

        if (in_array(self::MINIMISED, $context)) {
            return $normalised;
        }

        $normalised['managedGroupIds'] = array_values($data->getManagedGroupIds());
        $normalised['admin'] = $data->isAdmin();
        $normalised['verified'] = $data->isVerified();
        $normalised['active'] = $data->isActive();

        return array_merge($normalised, $this->normalizeMetadata($data));
    }
}
