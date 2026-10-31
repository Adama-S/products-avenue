<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Builds the PDO connection used to store comments (US-10, US-30).
 *
 * Every connection works in UTC: dates are stored in UTC and converted to
 * Europe/Paris only when displayed, so a stored time is never ambiguous
 * around a daylight saving change.
 */
final readonly class PdoFactory
{
    private const array REQUIRED_VARIABLES = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];

    public function __construct(
        private string $host,
        private string $database,
        private string $user,
        private string $password,
    ) {}

    /**
     * Reads the connection settings from the process environment (set by Docker Compose).
     *
     * @throws MissingConfigurationException when a setting is missing or empty
     */
    public static function fromEnvironment(): self
    {
        return self::fromArray(getenv());
    }

    /**
     * @param array<string, string> $environment
     *
     * @throws MissingConfigurationException when a setting is missing or empty
     */
    public static function fromArray(array $environment): self
    {
        foreach (self::REQUIRED_VARIABLES as $name) {
            if (($environment[$name] ?? '') === '') {
                throw new MissingConfigurationException(\sprintf('Missing environment variable %s.', $name));
            }
        }

        return new self(
            $environment['DB_HOST'],
            $environment['DB_NAME'],
            $environment['DB_USER'],
            $environment['DB_PASSWORD'],
        );
    }

    /**
     * Opens a new connection.
     *
     * @throws \PDOException when the server cannot be reached or refuses the credentials
     */
    public function create(): PDO
    {
        $pdo = new PDO(
            \sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $this->host, $this->database),
            $this->user,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements: values never travel inside the SQL text.
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
