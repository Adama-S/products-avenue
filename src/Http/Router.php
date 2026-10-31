<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Minimal router: an HTTP method and a path pattern lead to a handler (US-22).
 *
 * Patterns use {name} for one path segment: '/products/{id}'. Parameters reach the
 * handler as strings; checking them (a positive id, for instance) is the controller's job.
 * Exact matches only: '/products/5/' is not '/products/5', one URL per page.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: \Closure(Request, array<string, string>): Response}> */
    private array $routes = [];

    /**
     * @param \Closure(Request, array<string, string>): Response $handler
     */
    public function get(string $pattern, \Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /**
     * @param \Closure(Request, array<string, string>): Response $handler
     */
    public function post(string $pattern, \Closure $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * @throws PageNotFoundException when no route matches the method and the path
     * @throws \Throwable whatever the handler throws: business exceptions go up to the Application
     */
    public function dispatch(Request $request): Response
    {
        // HEAD is a GET without a body (curl -I, crawlers): Apache drops the body.
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['regex'], $request->path, $matches) === 1) {
                $parameters = array_filter($matches, \is_string(...), ARRAY_FILTER_USE_KEY);

                return ($route['handler'])($request, $parameters);
            }
        }

        throw new PageNotFoundException(\sprintf('No route for %s %s.', $request->method, $request->path));
    }

    /**
     * @param \Closure(Request, array<string, string>): Response $handler
     */
    private function add(string $method, string $pattern, \Closure $handler): void
    {
        $regex = preg_replace('/\\\{([a-zA-Z]+)\\\}/', '(?<$1>[^/]+)', preg_quote($pattern, '#'));
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#', 'handler' => $handler];
    }
}
