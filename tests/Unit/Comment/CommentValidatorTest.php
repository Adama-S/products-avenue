<?php

declare(strict_types=1);

namespace Tests\Unit\Comment;

use App\Comment\CommentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommentValidatorTest extends TestCase
{
    private CommentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CommentValidator();
    }

    public function test_a_valid_comment_has_no_error(): void
    {
        $form = $this->validator->validate('Camille', 'Très pratique au quotidien.');

        self::assertTrue($form->isValid());
        self::assertSame([], $form->errors);
        self::assertSame('Camille', $form->author);
        self::assertSame('Très pratique au quotidien.', $form->content);
    }

    public function test_surrounding_spaces_are_removed(): void
    {
        $form = $this->validator->validate("  Camille \t", "\n  Bien.  \n");

        self::assertSame('Camille', $form->author);
        self::assertSame('Bien.', $form->content);
    }

    public function test_windows_line_breaks_count_as_one_character(): void
    {
        // Browsers send \r\n: without this, 999 visible characters could be refused.
        $form = $this->validator->validate('Camille', str_repeat('a', 499) . "\r\n" . str_repeat('b', 500));

        self::assertTrue($form->isValid());
        self::assertSame(str_repeat('a', 499) . "\n" . str_repeat('b', 500), $form->content);
    }

    /**
     * @return iterable<string, array{string|null, string|null, array<string, string>}>
     */
    public static function invalidComments(): iterable
    {
        yield 'both missing' => [null, null, ['author' => 'Indiquez votre nom.', 'content' => 'Écrivez votre avis.']];
        yield 'only spaces' => ['   ', "  \n ", ['author' => 'Indiquez votre nom.', 'content' => 'Écrivez votre avis.']];
        yield 'author too long' => [str_repeat('é', 101), 'Bien.', ['author' => '100 caractères maximum.']];
        yield 'content too long' => ['Camille', str_repeat('é', 1001), ['content' => "1\u{202f}000 caractères maximum."]];
    }

    /**
     * @param array<string, string> $expectedErrors
     */
    #[DataProvider('invalidComments')]
    public function test_an_invalid_comment_gets_an_error_per_field(?string $author, ?string $content, array $expectedErrors): void
    {
        $form = $this->validator->validate($author, $content);

        self::assertFalse($form->isValid());
        self::assertSame($expectedErrors, $form->errors);
    }

    public function test_text_that_is_not_utf8_is_refused(): void
    {
        // "è" sent in Latin-1 by a misconfigured client: MySQL would reject it with an error.
        $form = $this->validator->validate("Ren\xE9", "Tr\xE8s bien.");

        self::assertSame([
            'author' => 'Ce texte contient des caractères non valides.',
            'content' => 'Ce texte contient des caractères non valides.',
        ], $form->errors);
    }

    public function test_the_limits_are_counted_in_characters_not_bytes(): void
    {
        self::assertTrue($this->validator->validate(str_repeat('é', 100), str_repeat('é', 1000))->isValid());
    }

    public function test_the_input_is_kept_when_invalid(): void
    {
        $form = $this->validator->validate('', '  Mon avis  ');

        self::assertSame('Mon avis', $form->content);
    }
}
