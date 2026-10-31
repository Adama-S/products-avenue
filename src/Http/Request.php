<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The incoming HTTP request (US-22).
 *
 * The only place that reads $_SERVER, $_GET and $_POST: everything else receives a
 * Request, so controllers can be tested with a simulated one.
 */
final readonly class Request
{
    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     * @param array<string, string> $headers lowercase names
     */
    public function __construct(
        public string $method,
        public string $path,
        private array $query = [],
        private array $post = [],
        private array $headers = [],
    ) {}

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = \is_string($uri) ? parse_url($uri, PHP_URL_PATH) : null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (\is_string($name) && str_starts_with($name, 'HTTP_') && \is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
            }
        }

        return new self(
            \is_string($method) ? strtoupper($method) : 'GET',
            \is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            $headers,
        );
    }

    /**
     * A query string parameter: null when missing, '' when sent but not a single value
     * (?page[]=1), which no validator accepts.
     */
    public function query(string $name): ?string
    {
        return self::single($this->query, $name);
    }

    /**
     * A form field: null when missing, '' when sent but not a single value.
     */
    public function post(string $name): ?string
    {
        return self::single($this->post, $name);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function single(array $values, string $name): ?string
    {
        if (!\array_key_exists($name, $values)) {
            return null;
        }

        return \is_string($values[$name]) ? $values[$name] : '';
    }
}
