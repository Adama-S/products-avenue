<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Uses the real PHP session; phpunit.xml disables session cookies so it can start in the console.
 */
final class CsrfTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        self::resetSession();
        $this->csrf = new Csrf();
    }

    protected function tearDown(): void
    {
        self::resetSession();
    }

    public function test_the_token_is_long_and_random(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->csrf->token());
    }

    public function test_the_token_stays_the_same_during_the_session(): void
    {
        // Several forms on one page, or two tabs: they all share the session token.
        self::assertSame($this->csrf->token(), $this->csrf->token());
    }

    public function test_the_token_of_the_session_is_valid(): void
    {
        self::assertTrue($this->csrf->isValid($this->csrf->token()));
    }

    public function test_another_token_is_refused(): void
    {
        $this->csrf->token();

        self::assertFalse($this->csrf->isValid(str_repeat('0', 64)));
        self::assertFalse($this->csrf->isValid(''));
        self::assertFalse($this->csrf->isValid(null));
    }

    public function test_a_token_without_a_session_token_is_refused(): void
    {
        // A form posted after the session expired, or forged on another site.
        self::assertFalse($this->csrf->isValid(str_repeat('a', 64)));
    }

    public function test_a_new_session_gets_a_new_token(): void
    {
        $first = $this->csrf->token();
        self::resetSession();

        self::assertNotSame($first, $this->csrf->token());
    }

    private static function resetSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
}
