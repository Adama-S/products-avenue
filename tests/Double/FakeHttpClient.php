<?php

declare(strict_types=1);

namespace Tests\Double;

use App\Http\Client\HttpClientInterface;
use App\Http\Client\HttpResponse;
use App\Http\Client\HttpTransportException;

/**
 * Plays back prepared replies, in order, and records the requested URLs: no network in tests.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<HttpResponse|HttpTransportException> */
    private array $replies;

    /** @var list<string> */
    public private(set) array $requestedUrls = [];

    public function __construct(HttpResponse|HttpTransportException ...$replies)
    {
        $this->replies = array_values($replies);
    }

    public static function json(mixed $data, int $statusCode = 200): HttpResponse
    {
        return new HttpResponse($statusCode, json_encode($data, JSON_THROW_ON_ERROR), 12.0);
    }

    public static function status(int $statusCode, string $body = ''): HttpResponse
    {
        return new HttpResponse($statusCode, $body, 12.0);
    }

    public static function timeout(): HttpTransportException
    {
        return new HttpTransportException('Operation timed out after 5000 milliseconds', 5000.0);
    }

    public function get(string $url): HttpResponse
    {
        $this->requestedUrls[] = $url;
        $reply = array_shift($this->replies) ?? throw new \LogicException('Unexpected HTTP call to ' . $url);

        if ($reply instanceof HttpTransportException) {
            throw $reply;
        }

        return $reply;
    }
}
