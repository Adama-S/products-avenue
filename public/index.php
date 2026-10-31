<?php

declare(strict_types=1);

/**
 * Front Controller and Composition Root (US-10, US-22, US-31).
 *
 * Every URL that is not a file reaches this script (FallbackResource). It is the only
 * place where concrete classes are chosen: the rest of the code depends on interfaces.
 * The Application handles routing, error pages and security headers.
 */

use App\Cache\FileCache;
use App\Cache\SystemClock;
use App\Comment\CommentRepository;
use App\Comment\CommentValidator;
use App\Config;
use App\Controller\CommentController;
use App\Controller\HealthController;
use App\Controller\ProductController;
use App\Controller\ProductPage;
use App\Database\PdoFactory;
use App\Http\Application;
use App\Http\Client\CurlHttpClient;
use App\Http\Request;
use App\Product\CachedProductProvider;
use App\Product\HttpProductProvider;
use App\Product\Money;
use App\Product\Product;
use App\Security\Csrf;
use App\View\View;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$config = Config::fromEnvironment();
$database = PdoFactory::fromEnvironment();
$view = new View($root . '/templates', $config->appUrl);

$products = new CachedProductProvider(
    new HttpProductProvider(new CurlHttpClient(), $config->productsApiUrl, $config->currency),
    // Only product objects may be rebuilt from a cache file.
    new FileCache($root . '/var/cache/products', new SystemClock(), [Product::class, Money::class]),
    $config->cacheTtl,
);
// The connection opens on the first query: see CommentRepository.
$comments = new CommentRepository($database->create(...));
$csrf = new Csrf();
$productPage = new ProductPage($comments, $csrf, $view);

$application = new Application(
    new ProductController($products, $comments, $view, $productPage, $config->productsPerPage),
    new CommentController($products, $comments, new CommentValidator(), $csrf, $productPage, $view),
    new HealthController($database),
    $view,
);

$application->handle(Request::fromGlobals())->send();
