<?php

/**
 * @author bsteffan
 * @since 2025-04-29
 */

namespace App\Tests\Service\Utility;

use App\Service\Utility\Base64UrlHelper;
use PHPUnit\Framework\TestCase;

class Base64UrlHelperTest extends TestCase
{
    public function testEncodeDecode(): void
    {
        $helper = new Base64UrlHelper();
        $data = "Hello World";
        $encoded = $helper->encode($data);
        $decoded = $helper->decode($encoded);
        $this->assertEquals($data, $decoded);
    }
}
