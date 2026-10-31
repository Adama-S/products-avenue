<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Comment\CommentRepository;
use App\Controller\HealthController;
use App\Controller\ProductController;
use App\Database\PdoFactory;
use App\Http\Application;
use App\Product\ProductProviderInterface;
use App\View\View;
use PDO;

/**
 * Builds the application as public/index.php does, with a fake product provider
 * and the catalog_test database: the same routes, controllers and templates.
 */
final readonly class TestApplication
{
    public const string APP_URL = 'https://products-avenue.test';

    public const int PRODUCTS_PER_PAGE = 10;

    public static function create(ProductProviderInterface $products, PDO $pdo): Application
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates', self::APP_URL);
        $comments = new CommentRepository(static fn(): PDO => $pdo);

        return new Application(
            new ProductController($products, $comments, $view, self::PRODUCTS_PER_PAGE),
            new HealthController(PdoFactory::fromEnvironment()),
            $view,
        );
    }
}
