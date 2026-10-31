<?php

declare(strict_types=1);

/**
 * Front Controller and Composition Root (US-10).
 *
 * Skeleton version: security headers, a placeholder home page and a /health
 * endpoint used by the Docker healthcheck. The Request, Router and controllers
 * replace the routing below with US-22 and US-23.
 */

use App\Database\DatabaseUnavailableException;
use App\Database\MissingConfigurationException;
use App\Database\PdoFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

// Security headers, sent once here for every response.
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://fakestoreapi.com; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Temporary: only Request::fromGlobals() will read $_SERVER once US-22 is done.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : '/';

if ($path === '/health') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    try {
        PdoFactory::fromEnvironment()->create()->query('SELECT 1');
        $database = 'up';
    } catch (DatabaseUnavailableException|PDOException|MissingConfigurationException $exception) {
        // The cause goes to the logs (docker compose logs app), never to the client.
        error_log('Health check: database unavailable: ' . $exception->getMessage());
        $database = 'down';
    }

    http_response_code($database === 'up' ? 200 : 503);
    echo json_encode(['status' => $database === 'up' ? 'ok' : 'degraded', 'database' => $database], JSON_THROW_ON_ERROR);

    return;
}

if ($path !== '/') {
    http_response_code(404);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Products Avenue</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="skeleton">
    <h1><strong>Products</strong> Avenue</h1>
    <p><?= $path === '/' ? 'Le squelette fonctionne. Le catalogue arrive avec US-22.' : 'Page introuvable.' ?></p>
    <p><a href="/health">État de l'application</a></p>
</main>
</body>
</html>
