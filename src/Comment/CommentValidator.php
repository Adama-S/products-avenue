<?php

declare(strict_types=1);

namespace App\Comment;

/**
 * Rules of the review form, shared by adding and editing (US-31).
 *
 * The limits are the column sizes of the comments table (VARCHAR(100), VARCHAR(1000)),
 * counted in characters like MySQL: an accented letter counts as one. The database CHECKs
 * stay as a last line of defence.
 */
final readonly class CommentValidator
{
    public const int AUTHOR_MAX_LENGTH = 100;

    public const int CONTENT_MAX_LENGTH = 1000;

    /**
     * @param string|null $author null when the field was not sent
     * @param string|null $content null when the field was not sent
     */
    public function validate(?string $author, ?string $content): CommentForm
    {
        // Browsers send line breaks as \r\n: counted as one character, as the visitor sees them.
        $author = trim(str_replace("\r\n", "\n", $author ?? ''));
        $content = trim(str_replace("\r\n", "\n", $content ?? ''));

        $errors = array_filter([
            'author' => self::error($author, self::AUTHOR_MAX_LENGTH, 'Indiquez votre nom.', '100 caractères maximum.'),
            'content' => self::error($content, self::CONTENT_MAX_LENGTH, 'Écrivez votre avis.', "1\u{202f}000 caractères maximum."),
        ]);

        return new CommentForm($author, $content, $errors);
    }

    /**
     * @return string|null the error message, null when the value is valid
     */
    private static function error(string $value, int $maxLength, string $emptyMessage, string $tooLongMessage): ?string
    {
        // Not UTF-8 (a misconfigured or malicious client): MySQL would refuse it with an error.
        if (!mb_check_encoding($value, 'UTF-8')) {
            return 'Ce texte contient des caractères non valides.';
        }

        if ($value === '') {
            return $emptyMessage;
        }

        return mb_strlen($value) > $maxLength ? $tooLongMessage : null;
    }
}
