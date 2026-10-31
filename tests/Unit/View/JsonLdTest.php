<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use App\View\View;
use PHPUnit\Framework\TestCase;

final class JsonLdTest extends TestCase
{
    public function test_it_encodes_structured_data_as_json(): void
    {
        $json = View::jsonLd(['@type' => 'Product', 'name' => 'Sac à dos', 'url' => 'https://products-avenue.test/products/1']);

        self::assertSame('{"@type":"Product","name":"Sac à dos","url":"https://products-avenue.test/products/1"}', $json);
    }

    public function test_a_value_cannot_close_the_script_element(): void
    {
        // A product title from the API ending the <script> block would inject HTML into the page.
        $json = View::jsonLd(['name' => '</script><script>alert(1)</script>']);

        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('>', $json);
        self::assertSame(['name' => '</script><script>alert(1)</script>'], json_decode($json, true));
    }
}
