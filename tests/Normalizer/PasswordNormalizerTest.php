<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Normalizer;

use App\Entity\Password;
use App\Normalizer\PasswordNormalizer;
use App\Repository\PasswordRepository;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class PasswordNormalizerTest extends KernelTestCase
{
    /**
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    #[DataProvider('provideNormalizationCases')]
    public function testNormalize(array $context, array $expected): void
    {
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->getContainer()->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find("aaaccaaa-bbbb-cccc-dddd-000000000000");
        if (!in_array(PasswordNormalizer::MINIMISED, $context) && !in_array(PasswordNormalizer::FOR_SEARCH, $context)) {
            $expected['createdAt'] = $password->getCreatedAt()->format("Y-m-d H:i:s");
        }
        $normalizer = $this->getContainer()->get('serializer');
        $actual = $normalizer->normalize($password, context: $context);
        $this->assertEquals($expected, $actual);
    }

    #[ArrayShape([
        'no context' => "array",
        'minimised' => "array",
        'with vault' => "array",
        'with folder' => "array",
        'with groups' => "array",
        'for search' => 'array',
    ])]
    public static function provideNormalizationCases(): array
    {
        return [
            'no context' => [
                [],
                [
                    'id' => 'aaaccaaa-bbbb-cccc-dddd-000000000000',
                    'title' => 'Customer-0 Password-0',
                    'target' => 'https://customer-0.example.com',
                    'location' => "floor 0, rack 1",
                    'externalId' => "0",
                    'description' => 'Auto-generated password 0 for customer 0',
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                ],
            ],
            'minimised' => [
                [PasswordNormalizer::MINIMISED],
                [
                    'id' => 'aaaccaaa-bbbb-cccc-dddd-000000000000',
                    'title' => 'Customer-0 Password-0',
                ],
            ],
            'with vault' => [
                [PasswordNormalizer::WITH_VAULT],
                [
                    'id' => 'aaaccaaa-bbbb-cccc-dddd-000000000000',
                    'title' => 'Customer-0 Password-0',
                    'target' => 'https://customer-0.example.com',
                    'location' => "floor 0, rack 1",
                    'externalId' => "0",
                    'description' => 'Auto-generated password 0 for customer 0',
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'vault' => [
                        'id' => "0aaaaaaa-bbbb-cccc-dddd-000000000000",
                        'name' => "Customers",
                        'private' => false,
                        'iconName' => "people",
                        'allowPasswordsAtRoot' => false,
                    ],
                ],
            ],
            'with folder' => [
                [PasswordNormalizer::WITH_FOLDER],
                [
                    'id' => 'aaaccaaa-bbbb-cccc-dddd-000000000000',
                    'title' => 'Customer-0 Password-0',
                    'target' => 'https://customer-0.example.com',
                    'location' => "floor 0, rack 1",
                    'externalId' => "0",
                    'description' => 'Auto-generated password 0 for customer 0',
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'folder' => [
                        'id' => "aaaaaaaa-bbbb-cccc-dddd-fc0000000000",
                        'name' => "Customer-0",
                        'iconName' => "folder",
                    ],
                ],
            ],
            'with groups' => [
                [PasswordNormalizer::WITH_GROUPS],
                [
                    'id' => 'aaaccaaa-bbbb-cccc-dddd-000000000000',
                    'title' => 'Customer-0 Password-0',
                    'target' => 'https://customer-0.example.com',
                    'description' => 'Auto-generated password 0 for customer 0',
                    'location' => "floor 0, rack 1",
                    'externalId' => "0",
                    'createdBy' => 'fixtures',
                    'updatedAt' => null,
                    'updatedBy' => null,
                    'groups' => [
                        [
                            'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                            'name' => "Administrators",
                            'canWrite' => true,
                        ],
                        [
                            'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000001",
                            'name' => "Managers",
                            'canWrite' => true,
                        ],
                        [
                            'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000002",
                            'name' => "Users",
                            'canWrite' => false,
                        ],
                        [
                            'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000004",
                            'name' => "Developers",
                            'canWrite' => true,
                        ],
                    ],
                    'users' => [],
                ],
            ],
            'for search' => [
                [PasswordNormalizer::FOR_SEARCH],
                [
                    'id' => 'aaaccaaa-bbbb-cccc-dddd-000000000000',
                    'title' => 'Customer-0 Password-0',
                    'target' => 'https://customer-0.example.com',
                    'externalId' => "0",
                    'vault' => [
                        'id' => "0aaaaaaa-bbbb-cccc-dddd-000000000000",
                        'name' => "Customers",
                        'private' => false,
                        'iconName' => "people",
                        'allowPasswordsAtRoot' => false,
                    ],
                    'folder' => [
                        'id' => "aaaaaaaa-bbbb-cccc-dddd-fc0000000000",
                        'name' => "Customer-0",
                        'iconName' => "folder",
                    ],
                ],
            ],
        ];
    }
}
