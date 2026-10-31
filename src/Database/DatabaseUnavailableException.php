<?php

declare(strict_types=1);

namespace App\Database;

/**
 * MySQL cannot be reached: the visitor sees a 503 page, the cause is in the logs (US-30).
 */
final class DatabaseUnavailableException extends \RuntimeException {}
