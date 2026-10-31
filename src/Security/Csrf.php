<?php

declare(strict_types=1);

namespace App\Security;

/**
 * CSRF protection of the review forms (US-31). The only class that touches the session.
 *
 * Every form carries a random token stored in the visitor's session; a form posted from
 * another site cannot know it. The session starts only here, on pages with a form: the
 * catalogue never opens one, so it sets no cookie and stays cacheable.
 */
final readonly class Csrf
{
    public const string FIELD = 'csrf_token';

    private const string SESSION_KEY = 'csrf_token';

    /**
     * The token of the current session, created on first use (64 hexadecimal characters).
     */
    public function token(): string
    {
        $this->startSession();
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!\is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /**
     * Compared with hash_equals: constant time, so the comparison leaks nothing about the token.
     */
    public function isValid(?string $token): bool
    {
        $this->startSession();
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return \is_string($expected) && $token !== null && hash_equals($expected, $token);
    }

    private function startSession(): void
    {
        // Cookie options (HttpOnly, SameSite=Lax, strict mode) are set in php.ini.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
