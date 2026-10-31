<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Comment\CommentRepository;
use App\Database\PdoFactory;
use App\Http\Application;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Double\FakeProductProvider;

/**
 * Common ground of the functional tests: the application is called with simulated requests,
 * a fake product provider and the catalog_test database (empty comments table).
 * Logs are kept out of the test output, and no session is left by another test.
 */
abstract class FunctionalTestCase extends TestCase
{
    protected PDO $pdo;

    protected FakeProductProvider $products;

    protected Application $application;

    private string $logFile;

    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
        self::resetSession();

        $this->pdo = PdoFactory::fromEnvironment()->create();
        $this->pdo->exec('TRUNCATE TABLE comments');
        $this->givenProducts(FakeProductProvider::withIds(...range(1, 20)));
    }

    protected function tearDown(): void
    {
        self::resetSession();
        ini_set('error_log', (string) $this->previousErrorLog);
        unlink($this->logFile);
    }

    /**
     * The products the API will return; the application is rebuilt around them.
     */
    protected function givenProducts(FakeProductProvider $products): void
    {
        $this->products = $products;
        $this->application = TestApplication::create($products, new CommentRepository(fn(): PDO => $this->pdo));
    }

    /**
     * @param array<string, string> $headers lowercase names
     */
    protected function get(string $uri, array $headers = []): Response
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return $this->application->handle(new Request('GET', $path, $query, headers: $headers));
    }

    /**
     * Posts a form as a browser would: with the CSRF token of the session.
     *
     * @param array<string, string> $fields
     */
    protected function postForm(string $path, array $fields): Response
    {
        return $this->post($path, [Csrf::FIELD => new Csrf()->token()] + $fields);
    }

    /**
     * Posts exactly these fields: to test a forged or expired form.
     *
     * @param array<string, string> $fields
     */
    protected function post(string $path, array $fields): Response
    {
        return $this->application->handle(new Request('POST', $path, post: $fields));
    }

    /**
     * A review written straight in the database, with a chosen date (UTC) to test the order.
     * Its content is « Content of {author} ».
     */
    protected function insertReview(int $productId, string $author = 'Camille', string $createdAt = '2026-10-01 10:00:00', ?string $updatedAt = null): int
    {
        $statement = $this->pdo->prepare('INSERT INTO comments (product_external_id, author, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$productId, $author, 'Content of ' . $author, $createdAt, $updatedAt]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * « Author 1 » to « Author N », one per day from 1 October: Author 1 is the oldest.
     *
     * @return list<int> their ids, oldest first
     */
    protected function insertReviews(int $productId, int $count): array
    {
        $ids = [];
        for ($day = 1; $day <= $count; ++$day) {
            $ids[] = $this->insertReview($productId, 'Author ' . $day, \sprintf('2026-10-%02d 10:00:00', $day));
        }

        return $ids;
    }

    /**
     * @return list<array{string, string}> author and content of every stored review
     */
    protected function storedReviews(): array
    {
        $statement = $this->pdo->query('SELECT author, content FROM comments ORDER BY id');
        self::assertNotFalse($statement);

        return array_values(array_map(static fn(array $row): array => [(string) $row['author'], (string) $row['content']], $statement->fetchAll()));
    }

    /**
     * The part of the page matched by a regular expression (a navigation, for instance).
     */
    protected static function fragment(string $body, string $pattern): string
    {
        preg_match($pattern, $body, $matches);

        return $matches[0] ?? self::fail(\sprintf('Nothing matches %s in the page.', $pattern));
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
