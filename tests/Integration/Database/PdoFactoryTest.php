<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Database\PdoFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real MySQL container, on catalog_test (forced in phpunit.xml).
 */
final class PdoFactoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = PdoFactory::fromEnvironment()->create();
    }

    public function test_tests_use_the_test_database(): void
    {
        self::assertSame('catalog_test', $this->fetchValue('SELECT DATABASE()'));
    }

    public function test_the_connection_works_in_utc(): void
    {
        self::assertSame('+00:00', $this->fetchValue('SELECT @@session.time_zone'));
    }

    public function test_prepared_statements_are_not_emulated(): void
    {
        self::assertFalse($this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    private function fetchValue(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
