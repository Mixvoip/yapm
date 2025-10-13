<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Controller\Vault;

use App\Tests\Cases\WebTestCase;

class GetStructureControllerTest extends WebTestCase
{
    public function testSuccessfulGet(): void
    {
        $this->getAsUser("/vaults/1aaaaaaa-bbbb-cccc-dddd-000000000000/structure", [], "dev@example.com");
        $this->assertResponseStatusCodeSame(200);
    }
}
