<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Normalizer;

use App\Entity\Vault;
use App\Normalizer\VaultNormalizer;
use App\Repository\VaultRepository;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VaultNormalizerTest extends KernelTestCase
{
    #[DataProvider('provideNormalizationCases')]
    public function testNormalize(array $context, array $expected): void
    {
        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $this->getContainer()->get('doctrine')->getRepository(Vault::class);
        $vault = $vaultRepository->find("0aaaaaaa-bbbb-cccc-dddd-000000000000");
        if (!in_array(VaultNormalizer::MINIMISED, $context)) {
            $expected['createdAt'] = $vault->getCreatedAt()->format("Y-m-d H:i:s");
        }
        $normalizer = $this->getContainer()->get('serializer');
        $actual = $normalizer->normalize($vault, context: $context);
        $this->assertEquals($expected, $actual);
    }

    #[ArrayShape([
        'no context' => "array",
        'minimised' => "array",
        'with groups' => "array",
        'with mandatory fields' => "array",
    ])]
    public static function provideNormalizationCases(): array
    {
        return [
            'no context' => [
                [],
                [
                    'id' => '0aaaaaaa-bbbb-cccc-dddd-000000000000',
                    'name' => 'Customers',
                    'private' => false,
                    'iconName' => "people",
                    'allowPasswordsAtRoot' => false,
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                ],
            ],
            'minimised' => [
                [VaultNormalizer::MINIMISED],
                [
                    'id' => '0aaaaaaa-bbbb-cccc-dddd-000000000000',
                    'name' => 'Customers',
                    'private' => false,
                    'iconName' => "people",
                    'allowPasswordsAtRoot' => false,
                ],
            ],
            'with groups' => [
                [VaultNormalizer::WITH_GROUPS],
                [
                    'id' => '0aaaaaaa-bbbb-cccc-dddd-000000000000',
                    'name' => 'Customers',
                    'private' => false,
                    'iconName' => "people",
                    'allowPasswordsAtRoot' => false,
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'groups' => [
                        [
                            'id' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000',
                            'name' => 'Administrators',
                            'canWrite' => true,
                            'partial' => false,
                        ],
                        [
                            'id' => 'aaaaaaaa-bbbb-cccc-dddd-900000000001',
                            'name' => 'Managers',
                            'canWrite' => true,
                            'partial' => false,
                        ],
                        [
                            'id' => 'aaaaaaaa-bbbb-cccc-dddd-900000000002',
                            'name' => 'Users',
                            'canWrite' => false,
                            'partial' => false,
                        ],
                        [
                            'id' => 'aaaaaaaa-bbbb-cccc-dddd-900000000004',
                            'name' => 'Developers',
                            'canWrite' => true,
                            'partial' => false,
                        ],
                    ],
                ],
            ],
            'with mandatory fields' => [
                [VaultNormalizer::WITH_MANDATORY_FIELDS],
                [
                    'id' => '0aaaaaaa-bbbb-cccc-dddd-000000000000',
                    'name' => 'Customers',
                    'private' => false,
                    'iconName' => "people",
                    'allowPasswordsAtRoot' => false,
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'mandatoryFolderFields' => ["external_id"],
                    'mandatoryPasswordFields' => ["location"],
                ],
            ],
        ];
    }
}
