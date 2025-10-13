<?php

/**
 * @author bsteffan
 * @since 2025-06-18
 */

namespace App\Tests\Controller\Folder;

use App\Tests\Cases\WebTestCase;

class GetStructureControllerTest extends WebTestCase
{
    public function testSuccessfulGet(): void
    {
        $this->getAsUser("/folders/aaaaaaaa-bbbb-cccc-dddd-fd0000000000/structure", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(200);
    }
}
