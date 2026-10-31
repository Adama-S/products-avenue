<?php

declare(strict_types=1);

namespace App\Comment;

use PDO;

/**
 * All the SQL of the reviews (US-30): prepared statements only, no user input in the SQL text.
 *
 * A review always belongs to the product of the URL: reads, updates and deletes filter on
 * the review id AND the product id, so /products/2/comments/{id of a review of product 1}
 * finds nothing. The product itself is not in this database (FakeStore API): checking that
 * it exists before saving is the caller's job.
 */
final readonly class CommentRepository
{
    private const string COLUMNS = 'id, product_external_id, author, content, created_at, updated_at';

    public function __construct(private PDO $pdo) {}

    /**
     * One page of the reviews of a product, newest first.
     *
     * @return list<Comment>
     */
    public function findPageByProduct(int $productId, int $limit, int $offset): array
    {
        // id DESC breaks ties between reviews of the same second: without it, a review could
        // show on two pages or on none. Served by idx_comments_product_created (InnoDB appends id).
        // OFFSET re-reads the skipped rows: fine for a few hundred reviews per product.
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM comments WHERE product_external_id = ?'
            . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
        );
        $statement->bindValue(1, $productId, PDO::PARAM_INT);
        $statement->bindValue(2, $limit, PDO::PARAM_INT);
        $statement->bindValue(3, $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map($this->hydrate(...), $statement->fetchAll()));
    }

    public function countByProduct(int $productId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM comments WHERE product_external_id = ?');
        $statement->execute([$productId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Review counts of a whole catalogue page, in a single query.
     *
     * One query per product card would be the N+1 trap: 30 cards, 30 round trips.
     * Here one IN (...) GROUP BY, served by idx_comments_product_created.
     *
     * @param list<int> $productIds
     *
     * @return array<int, int> count per product id, 0 for a product without reviews
     */
    public function countByProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, \count($productIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT product_external_id, COUNT(*) FROM comments WHERE product_external_id IN ({$placeholders})"
            . ' GROUP BY product_external_id',
        );
        $statement->execute($productIds);

        $counts = array_fill_keys($productIds, 0);
        foreach ($statement->fetchAll(PDO::FETCH_KEY_PAIR) as $productId => $count) {
            $counts[(int) $productId] = (int) $count;
        }

        return $counts;
    }

    /**
     * @return Comment|null null when the review does not exist or belongs to another product
     */
    public function findForProduct(int $commentId, int $productId): ?Comment
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM comments WHERE id = ? AND product_external_id = ?');
        $statement->execute([$commentId, $productId]);
        $row = $statement->fetch();

        return \is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return int the id of the new review
     *
     * @throws \PDOException when the database refuses the data (CHECK constraints, lengths)
     */
    public function create(int $productId, string $author, string $content): int
    {
        $statement = $this->pdo->prepare('INSERT INTO comments (product_external_id, author, content) VALUES (?, ?, ?)');
        $statement->execute([$productId, $author, $content]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Does nothing when the review does not belong to this product.
     * updated_at is set by MySQL (ON UPDATE CURRENT_TIMESTAMP), only if a value changed.
     *
     * @throws \PDOException when the database refuses the data (CHECK constraints, lengths)
     */
    public function update(int $commentId, int $productId, string $author, string $content): void
    {
        $statement = $this->pdo->prepare('UPDATE comments SET author = ?, content = ? WHERE id = ? AND product_external_id = ?');
        $statement->execute([$author, $content, $commentId, $productId]);
    }

    /**
     * Does nothing when the review does not belong to this product.
     */
    public function delete(int $commentId, int $productId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM comments WHERE id = ? AND product_external_id = ?');
        $statement->execute([$commentId, $productId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Comment
    {
        $utc = new \DateTimeZone('UTC');

        return new Comment(
            (int) $row['id'],
            (int) $row['product_external_id'],
            (string) $row['author'],
            (string) $row['content'],
            new \DateTimeImmutable((string) $row['created_at'], $utc),
            $row['updated_at'] === null ? null : new \DateTimeImmutable((string) $row['updated_at'], $utc),
        );
    }
}
