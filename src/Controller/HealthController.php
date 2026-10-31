<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DatabaseUnavailableException;
use App\Database\PdoFactory;
use App\Http\Response;

/**
 * /health, used by the Docker healthcheck (US-10, US-22).
 */
final readonly class HealthController
{
    public function __construct(private PdoFactory $database) {}

    /**
     * 200 when MySQL answers, 503 otherwise; the cause goes to the logs, never to the client.
     */
    public function check(): Response
    {
        try {
            $this->database->create()->query('SELECT 1');
            $up = true;
        } catch (DatabaseUnavailableException|\PDOException $exception) {
            error_log('Health check: database unavailable: ' . $exception->getMessage());
            $up = false;
        }

        return new Response(
            $up ? 200 : 503,
            json_encode(['status' => $up ? 'ok' : 'degraded', 'database' => $up ? 'up' : 'down'], JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'],
        );
    }
}
