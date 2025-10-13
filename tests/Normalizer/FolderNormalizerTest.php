<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Normalizer;

use App\Entity\Folder;
use App\Normalizer\FolderNormalizer;
use App\Repository\FolderRepository;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FolderNormalizerTest extends KernelTestCase
{
    #[DataProvider('provideNormalizationCases')]
    public function testNormalize(array $context, array $expected): void
    {
        /** @var FolderRepository $folderRepository */
        $folderRepository = $this->getContainer()->get('doctrine')->getRepository(Folder::class);
        $folder = $folderRepository->find("aaaaaaaa-bbbb-cccc-dddd-fd0000000004");
        if (!in_array(FolderNormalizer::MINIMISED, $context)) {
            $expected['createdAt'] = $folder->getCreatedAt()->format("Y-m-d H:i:s");
        }
        $normalizer = $this->getContainer()->get('serializer');
        $actual = $normalizer->normalize($folder, context: $context);
        $this->assertEquals($expected, $actual);
    }

    #[ArrayShape([
        'no context' => "array",
        'minimised' => "array",
        'with parent minimised' => "array",
        'with vault' => "array",
        'with groups' => "array",
    ])]
    public static function provideNormalizationCases(): array
    {
        return [
            'no context' => [
                [],
                [
                    'id' => 'aaaaaaaa-bbbb-cccc-dddd-fd0000000004',
                    'name' => 'yapm',
                    'externalId' => null,
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                ],
            ],
            'minimised' => [
                [FolderNormalizer::MINIMISED],
                [
                    'id' => 'aaaaaaaa-bbbb-cccc-dddd-fd0000000004',
                    'name' => 'yapm',
                ],
            ],
            'with parent minimised' => [
                [
                    FolderNormalizer::WITH_PARENT,
                    FolderNormalizer::MINIMISED,
                ],
                [
                    'id' => 'aaaaaaaa-bbbb-cccc-dddd-fd0000000004',
                    'name' => 'yapm',
                    'parent' => [
                        'id' => 'aaaaaaaa-bbbb-cccc-dddd-fd0000000003',
                        'name' => 'nexus',
                        'parent' => null,
                    ],
                ],
            ],
            'with vault' => [
                [FolderNormalizer::WITH_VAULT],
                [
                    'id' => 'aaaaaaaa-bbbb-cccc-dddd-fd0000000004',
                    'name' => 'yapm',
                    'externalId' => null,
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'vault' => [
                        'id' => "1aaaaaaa-bbbb-cccc-dddd-000000000000",
                        'name' => "Development",
                        'private' => false,
                        'iconName' => "code",
                        'allowPasswordsAtRoot' => true,
                    ],
                ],
            ],
            'with groups' => [
                [FolderNormalizer::WITH_GROUPS],
                [
                    'id' => 'aaaaaaaa-bbbb-cccc-dddd-fd0000000004',
                    'name' => 'yapm',
                    'externalId' => null,
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'groups' => [
                        [
                            'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                            'name' => "Administrators",
                            'canWrite' => "true",
                            'partial' => false,
                        ],
                        [
                            'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000004",
                            'name' => "Developers",
                            'canWrite' => "true",
                            'partial' => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}
