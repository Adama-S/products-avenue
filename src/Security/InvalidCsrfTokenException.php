<?php

declare(strict_types=1);

namespace App\Security;

/**
 * A form came without the token of the session: forged on another site, or posted after
 * the session expired. Nothing is saved; the visitor sees « Formulaire expiré » (403) (US-31).
 */
final class InvalidCsrfTokenException extends \RuntimeException {}
