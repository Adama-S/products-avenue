<?php

declare(strict_types=1);

namespace App\Http\Client;

/**
 * No HTTP response at all: DNS or connection failure, timeout (US-20).
 * Carries how long the attempt lasted, for the logs.
 */
final class HttpTransportException extends \RuntimeException
{
    public function __construct(string $message, public readonly float $durationMs)
    {
        parent::__construct($message);
    }
}
