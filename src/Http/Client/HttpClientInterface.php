<?php

declare(strict_types=1);

namespace App\Http\Client;

/**
 * Minimal HTTP client: one GET, the answer as it came (US-20).
 *
 * Deciding what a status code means (retry, not found, unavailable) is the caller's job.
 * An interface so that tests use a fake client and never touch the network.
 */
interface HttpClientInterface
{
    /**
     * Any HTTP status is returned as a response, 4xx and 5xx included.
     *
     * @throws HttpTransportException when no response was received (connection failure, timeout)
     */
    public function get(string $url): HttpResponse;
}
