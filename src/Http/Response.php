<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The HTTP response built by a controller, sent once by the front controller (US-22).
 * A plain value: tests read its status, headers and body without any output.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $statusCode,
        public string $body = '',
        public array $headers = [],
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public static function html(int $statusCode, string $body, array $headers = []): self
    {
        return new self($statusCode, $body, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    /**
     * @param array<string, string> $headers added, or replacing those with the same name
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->statusCode, $this->body, $headers + $this->headers);
    }

    /**
     * Writes the status, the headers and the body: called once, by public/index.php.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
