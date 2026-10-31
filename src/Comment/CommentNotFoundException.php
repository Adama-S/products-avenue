<?php

declare(strict_types=1);

namespace App\Comment;

/**
 * The review does not exist, or belongs to another product (US-31).
 * Both cases give the same « Avis introuvable » 404: nothing tells them apart.
 */
final class CommentNotFoundException extends \RuntimeException {}
