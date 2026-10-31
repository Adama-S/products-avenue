<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\MissingConfigurationException;
use App\Database\PdoFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdoFactoryTest extends TestCase
{
    private const array VALID_ENVIRONMENT = [
        'DB_HOST' => 'db',
        'DB_NAME' => 'catalog',
        'DB_USER' => 'app',
        'DB_PASSWORD' => 'secret',
    ];

    public function test_it_builds_from_a_complete_environment(): void
    {
        self::assertInstanceOf(PdoFactory::class, PdoFactory::fromArray(self::VALID_ENVIRONMENT));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredVariables(): iterable
    {
        foreach (array_keys(self::VALID_ENVIRONMENT) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('requiredVariables')]
    public function test_a_missing_variable_is_refused(string $name): void
    {
        $environment = self::VALID_ENVIRONMENT;
        unset($environment[$name]);

        $this->expectException(MissingConfigurationException::class);
        $this->expectExceptionMessage($name);

        PdoFactory::fromArray($environment);
    }

    #[DataProvider('requiredVariables')]
    public function test_an_empty_variable_is_refused(string $name): void
    {
        $environment = [$name => ''] + self::VALID_ENVIRONMENT;

        $this->expectException(MissingConfigurationException::class);

        PdoFactory::fromArray($environment);
    }
}
