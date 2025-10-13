<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Controller\Folder;

use App\Tests\Cases\WebTestCase;

class GetControllerTest extends WebTestCase
{
    public function testSuccessfulGet(): void
    {
        $this->getAsUser("/folders/aaaaaaaa-bbbb-cccc-dddd-fd0000000003", [], "dev@example.com");
        $this->assertResponseStatusCodeSame(200);
    }

    public function testSuccessfulCreate(): void
    {
        $body = [
            "name" => "Test Folder",
            "externalId" => "1234567890",
            "vaultId" => "0aaaaaaa-bbbb-cccc-dddd-000000000000",
            "groups" => [["groupId" => "aaaaaaaa-bbbb-cccc-dddd-900000000003", "canWrite" => true]],
        ];
        $this->postAsUser("/folders", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(201);
    }
}
