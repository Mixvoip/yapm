<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Normalizer;

use App\Entity\Password;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PasswordNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use MetadataNormalizerTrait;

    public const string MINIMISED = "password minimised";
    public const string WITH_FOLDER = "password with folder";
    public const string WITH_VAULT = "password with vault";
    public const string WITH_GROUPS = "password with groups";
    public const string FOR_SEARCH = "password for search";

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Password;
    }

    /**
     * @inheritDoc
     */
    #[ArrayShape([Password::class => "true"])]
    public function getSupportedTypes(?string $format): array
    {
        return [
            Password::class => true,
        ];
    }

    /**
     * @inheritDoc
     *
     * @param  Password  $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (in_array(self::FOR_SEARCH, $context, true)) {
            return $this->normalizeForSearch($data);
        }

        $normalised = [
            'id' => $data->getId(),
            'title' => $data->getTitle(),
        ];

        if (in_array(self::WITH_FOLDER, $context)) {
            $normalised['folder'] = $this->normalizer->normalize(
                $data->getFolder(),
                context: [FolderNormalizer::MINIMISED]
            );
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

            foreach ($data->getGroupPasswords() as $groupPassword) {
                $group = $groupPassword->getGroup();

                if ($group->isPrivate()) {
                    // This is a user share - get the user from the group
                    $groupUser = $group->getGroupUsers()->first();
                    if ($groupUser) {
                        $user = $groupUser->getUser();
                        $userShares[] = [
                            'id' => $user->getId(),
                            'email' => $user->getEmail(),
                            'username' => $user->getUsername(),
                            'canWrite' => $groupPassword->canWrite(),
                        ];
                    }
                } else {
                    // Regular group
                    $groups[] = [
                        'id' => $group->getId(),
                        'name' => $group->getName(),
                        'canWrite' => $groupPassword->canWrite(),
                    ];
                }
            }

            $normalised['groups'] = $groups;
            $normalised['users'] = $userShares;
        }

        if (in_array(self::MINIMISED, $context)) {
            return $normalised;
        }

        $normalised['target'] = $data->getTarget();
        $normalised['description'] = $data->getDescription();
        $normalised['location'] = $data->getLocation();
        $normalised['externalId'] = $data->getExternalId();

        return array_merge($normalised, $this->normalizeMetadata($data));
    }

    /**
     * Normalise a password for search response.
     *
     * @param  Password  $password
     *
     * @return array
     * @throws ExceptionInterface
     */
    #[ArrayShape([
        'id' => "string",
        'title' => "string",
        'target' => "null|string",
        'externalId' => "null|string",
        'vault' => "mixed",
        'folder' => "mixed",
    ])]
    private function normalizeForSearch(Password $password): array
    {
        return [
            'id' => $password->getId(),
            'title' => $password->getTitle(),
            'target' => $password->getTarget(),
            'externalId' => $password->getExternalId(),
            'vault' => $this->normalizer->normalize($password->getVault(), context: [VaultNormalizer::MINIMISED]),
            'folder' => $this->normalizer->normalize($password->getFolder(), context: [FolderNormalizer::MINIMISED]),
        ];
    }
}
