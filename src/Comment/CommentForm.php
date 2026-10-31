<?php

declare(strict_types=1);

namespace App\Comment;

/**
 * What the visitor typed in the review form, cleaned, with an error per invalid field (US-31).
 * Shown back in the form when it is refused, so nothing has to be typed again.
 */
final readonly class CommentForm
{
    /**
     * @param array<string, string> $errors message per field name (author, content)
     */
    public function __construct(
        public string $author = '',
        public string $content = '',
        public array $errors = [],
    ) {}

    public static function fromComment(Comment $comment): self
    {
        return new self($comment->author, $comment->content);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
