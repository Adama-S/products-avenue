<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class EscapeTest extends TestCase
{
    public function test_html_special_characters_are_escaped(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        self::assertSame('&quot; onmouseover=&quot;x', e('" onmouseover="x'));
        self::assertSame('l&#039;été &amp; co', e("l'été & co"));
    }

    public function test_numbers_are_accepted(): void
    {
        self::assertSame('42', e(42));
    }

    public function test_invalid_utf8_does_not_empty_the_output(): void
    {
        self::assertSame("caf\u{fffd}", e("caf\xE9"));
    }
}
