<?php

declare(strict_types=1);

namespace App\Comment;

/**
 * Confirmation shown after a review form, carried by the redirect URL: ?review=published (US-31).
 *
 * In the URL rather than in the session: only Csrf touches the session. A closed list, so
 * the URL can only pick one of these messages, never inject a text of its own.
 */
enum CommentNotice: string
{
    case Published = 'published';
    case Updated = 'updated';
    case Deleted = 'deleted';

    /**
     * The confirmation shown on the product page, in French.
     */
    public function message(): string
    {
        return match ($this) {
            self::Published => 'Votre avis est publié.',
            self::Updated => 'Votre avis est modifié.',
            self::Deleted => 'Votre avis est supprimé.',
        };
    }
}
