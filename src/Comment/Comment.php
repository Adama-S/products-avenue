<?php

declare(strict_types=1);

namespace App\Comment;

/**
 * A customer review of a FakeStore product, as stored in the comments table (US-30).
 * Dates are in UTC; templates convert them to Europe/Paris.
 */
final readonly class Comment
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $author,
        public string $content,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Shown as "modifié" next to the date.
     */
    public function isEdited(): bool
    {
        return $this->updatedAt !== null;
    }
}
