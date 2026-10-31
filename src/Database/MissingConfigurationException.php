<?php

declare(strict_types=1);

namespace App\Database;

/**
 * A required environment variable is missing: a deployment error, not a user error (US-10).
 */
final class MissingConfigurationException extends \RuntimeException {}
