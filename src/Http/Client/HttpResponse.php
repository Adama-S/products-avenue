<?php

declare(strict_types=1);

namespace App\Http\Client;

/**
 * A response received from a remote server (US-20).
 * The duration is kept for the logs: a slow API is the first sign of an outage.
 */
final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public float $durationMs,
    ) {}
}
