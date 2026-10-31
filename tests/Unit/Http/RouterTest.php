<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\PageNotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', static fn(): Response => new Response(200, 'home'));
        $this->router->get('/products/{id}', static fn(Request $request, array $parameters): Response => new Response(200, 'product ' . $parameters['id']));
        $this->router->post('/products/{productId}/comments', static fn(Request $request, array $parameters): Response => new Response(303, 'posted on ' . $parameters['productId']));
    }

    public function test_it_calls_the_handler_of_the_matching_route(): void
    {
        self::assertSame('home', $this->router->dispatch(new Request('GET', '/'))->body);
    }

    public function test_it_passes_the_path_parameters(): void
    {
        self::assertSame('product 5', $this->router->dispatch(new Request('GET', '/products/5'))->body);
        self::assertSame('posted on 7', $this->router->dispatch(new Request('POST', '/products/7/comments'))->body);
    }

    public function test_a_head_request_is_served_like_a_get(): void
    {
        // curl -I and some crawlers send HEAD; Apache drops the body itself.
        self::assertSame('home', $this->router->dispatch(new Request('HEAD', '/'))->body);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unknownRoutes(): iterable
    {
        yield 'unknown path' => ['GET', '/unknown'];
        yield 'trailing slash' => ['GET', '/products/5/'];
        yield 'extra segment' => ['GET', '/products/5/extra'];
        yield 'empty parameter' => ['GET', '/products/'];
        yield 'wrong method' => ['POST', '/products/5'];
        yield 'get on a post route' => ['GET', '/products/7/comments'];
    }

    #[DataProvider('unknownRoutes')]
    public function test_an_unknown_route_is_not_found(string $method, string $path): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->router->dispatch(new Request($method, $path));
    }
}
