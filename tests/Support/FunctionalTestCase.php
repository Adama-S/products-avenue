<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\PdoFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Common ground of the functional tests: an empty comments table in catalog_test,
 * logs kept out of the test output (and readable), no session left by another test.
 */
abstract class FunctionalTestCase extends TestCase
{
    protected PDO $pdo;

    private string $logFile;

    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
        self::resetSession();

        $this->pdo = PdoFactory::fromEnvironment()->create();
        $this->pdo->exec('TRUNCATE TABLE comments');
    }

    protected function tearDown(): void
    {
        self::resetSession();
        ini_set('error_log', (string) $this->previousErrorLog);
        unlink($this->logFile);
    }

    protected function logs(): string
    {
        return (string) file_get_contents($this->logFile);
    }

    private static function resetSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
}
