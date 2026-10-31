<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Comment\CommentRepository;
use App\Comment\CommentValidator;
use App\Controller\CommentController;
use App\Controller\HealthController;
use App\Controller\ProductController;
use App\Controller\ProductPage;
use App\Database\PdoFactory;
use App\Http\Application;
use App\Product\ProductProviderInterface;
use App\Security\Csrf;
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

        return self::withComments($products, new CommentRepository(static fn(): PDO => $pdo), $view);
    }

    /**
     * Same wiring with a repository of your choice (a database that is down, for instance).
     */
    public static function withComments(ProductProviderInterface $products, CommentRepository $comments, View $view): Application
    {
        $csrf = new Csrf();
        $productPage = new ProductPage($comments, $csrf, $view);

        return new Application(
            new ProductController($products, $comments, $view, $productPage, self::PRODUCTS_PER_PAGE),
            new CommentController($products, $comments, new CommentValidator(), $csrf, $productPage, $view),
            new HealthController(PdoFactory::fromEnvironment()),
            $view,
        );
    }
}
