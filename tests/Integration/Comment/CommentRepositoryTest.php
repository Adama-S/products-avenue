<?php

declare(strict_types=1);

namespace Tests\Integration\Comment;

use App\Comment\Comment;
use App\Comment\CommentRepository;
use App\Database\PdoFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real MySQL container, on catalog_test (forced in phpunit.xml).
 */
final class CommentRepositoryTest extends TestCase
{
    private PDO $pdo;

    private CommentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = PdoFactory::fromEnvironment()->create();
        $this->pdo->exec('TRUNCATE TABLE comments');
        $this->repository = new CommentRepository($this->pdo);
    }

    public function test_a_created_comment_can_be_read_back(): void
    {
        $id = $this->repository->create(5, 'Camille', 'Très pratique au quotidien 👍');

        $comment = $this->repository->findForProduct($id, 5);

        self::assertInstanceOf(Comment::class, $comment);
        self::assertSame($id, $comment->id);
        self::assertSame(5, $comment->productId);
        self::assertSame('Camille', $comment->author);
        self::assertSame('Très pratique au quotidien 👍', $comment->content);
        self::assertNull($comment->updatedAt);
        self::assertFalse($comment->isEdited());
    }

    public function test_dates_are_stored_and_read_in_utc(): void
    {
        $before = new \DateTimeImmutable('-2 seconds', new \DateTimeZone('UTC'));
        $comment = $this->repository->findForProduct($this->repository->create(5, 'Camille', 'Bien.'), 5);

        self::assertNotNull($comment);
        self::assertSame('UTC', $comment->createdAt->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $comment->createdAt);
        self::assertLessThanOrEqual(new \DateTimeImmutable('+2 seconds', new \DateTimeZone('UTC')), $comment->createdAt);
    }

    public function test_a_comment_of_another_product_is_not_found(): void
    {
        $id = $this->repository->create(5, 'Camille', 'Bien.');

        self::assertNull($this->repository->findForProduct($id, 6));
        self::assertNull($this->repository->findForProduct($id + 1, 5));
    }

    public function test_a_page_lists_the_comments_of_a_product_newest_first(): void
    {
        $this->insert(5, 'Oldest', '2026-10-01 10:00:00');
        $this->insert(5, 'Newest', '2026-10-03 10:00:00');
        $this->insert(6, 'Other product', '2026-10-04 10:00:00');
        $this->insert(5, 'Middle', '2026-10-02 10:00:00');

        self::assertSame(['Newest', 'Middle', 'Oldest'], $this->authors($this->repository->findPageByProduct(5, 10, 0)));
    }

    public function test_comments_of_the_same_second_keep_a_stable_order(): void
    {
        // Without the id tie-breaker, the order of these rows is undefined and could change between pages.
        foreach (['First', 'Second', 'Third'] as $author) {
            $this->insert(5, $author, '2026-10-01 10:00:00');
        }

        self::assertSame(['Third', 'Second', 'First'], $this->authors($this->repository->findPageByProduct(5, 10, 0)));
    }

    public function test_a_page_is_cut_with_limit_and_offset(): void
    {
        for ($day = 1; $day <= 12; ++$day) {
            $this->insert(5, 'Day ' . $day, \sprintf('2026-10-%02d 10:00:00', $day));
        }

        self::assertCount(10, $this->repository->findPageByProduct(5, 10, 0));
        self::assertSame(['Day 2', 'Day 1'], $this->authors($this->repository->findPageByProduct(5, 10, 10)));
        self::assertSame([], $this->repository->findPageByProduct(5, 10, 20));
    }

    public function test_it_counts_the_comments_of_one_product(): void
    {
        $this->repository->create(5, 'A', 'Bien.');
        $this->repository->create(5, 'B', 'Bien.');
        $this->repository->create(6, 'C', 'Bien.');

        self::assertSame(2, $this->repository->countByProduct(5));
        self::assertSame(0, $this->repository->countByProduct(7));
    }

    public function test_it_counts_the_comments_of_several_products_in_one_query(): void
    {
        $this->repository->create(1, 'A', 'Bien.');
        $this->repository->create(1, 'B', 'Bien.');
        $this->repository->create(3, 'C', 'Bien.');
        $this->repository->create(9, 'D', 'Not on this page.');

        $queriesBefore = $this->queryCount();
        $counts = $this->repository->countByProducts([1, 2, 3]);
        $queries = $this->queryCount() - $queriesBefore - 1; // the second SHOW STATUS counts itself

        self::assertSame([1 => 2, 2 => 0, 3 => 1], $counts);
        self::assertSame(1, $queries);
    }

    public function test_counting_no_product_runs_no_query(): void
    {
        $queriesBefore = $this->queryCount();

        self::assertSame([], $this->repository->countByProducts([]));
        self::assertSame(1, $this->queryCount() - $queriesBefore);
    }

    public function test_an_update_changes_the_comment_and_marks_it_as_edited(): void
    {
        $id = $this->insert(5, 'Camille', '2026-10-01 10:00:00');

        $this->repository->update($id, 5, 'Camille B.', 'Finalement, très bien.');
        $comment = $this->repository->findForProduct($id, 5);

        self::assertNotNull($comment);
        self::assertSame('Camille B.', $comment->author);
        self::assertSame('Finalement, très bien.', $comment->content);
        self::assertSame('2026-10-01 10:00:00', $comment->createdAt->format('Y-m-d H:i:s'));
        self::assertTrue($comment->isEdited());
    }

    public function test_an_update_through_another_product_changes_nothing(): void
    {
        $id = $this->insert(5, 'Camille', '2026-10-01 10:00:00');

        $this->repository->update($id, 6, 'Intruder', 'Changed.');

        self::assertSame('Camille', $this->repository->findForProduct($id, 5)?->author);
    }

    public function test_a_delete_removes_only_the_comment_of_that_product(): void
    {
        $id = $this->repository->create(5, 'Camille', 'Bien.');

        $this->repository->delete($id, 6);
        self::assertNotNull($this->repository->findForProduct($id, 5));

        $this->repository->delete($id, 5);
        self::assertNull($this->repository->findForProduct($id, 5));
    }

    public function test_the_database_refuses_an_empty_author_or_content(): void
    {
        // Last line of defence if the validator is bypassed: the CHECK constraints.
        foreach ([['   ', 'Bien.'], ['Camille', '   ']] as [$author, $content]) {
            try {
                $this->repository->create(5, $author, $content);
                self::fail('An empty field must be refused by the database.');
            } catch (\PDOException $exception) {
                self::assertStringContainsString('chk_comments_', $exception->getMessage());
            }
        }
    }

    public function test_the_database_keeps_the_same_length_limits_as_the_form(): void
    {
        $this->repository->create(5, str_repeat('é', 100), str_repeat('é', 1000));

        $this->expectException(\PDOException::class);

        $this->repository->create(5, 'Camille', str_repeat('é', 1001));
    }

    /**
     * Inserts a comment with a chosen creation date, to test the order.
     */
    private function insert(int $productId, string $author, string $createdAt): int
    {
        $statement = $this->pdo->prepare('INSERT INTO comments (product_external_id, author, content, created_at) VALUES (?, ?, ?, ?)');
        $statement->execute([$productId, $author, 'Content of ' . $author, $createdAt]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<Comment> $comments
     *
     * @return list<string>
     */
    private function authors(array $comments): array
    {
        return array_map(static fn(Comment $comment): string => $comment->author, $comments);
    }

    /**
     * Statements run by this connection so far (each SHOW STATUS counts itself).
     */
    private function queryCount(): int
    {
        $statement = $this->pdo->query("SHOW SESSION STATUS LIKE 'Questions'");
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn(1);
    }
}
