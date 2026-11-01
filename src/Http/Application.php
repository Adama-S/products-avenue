<?php

declare(strict_types=1);

namespace App\Http;

use App\Comment\CommentNotFoundException;
use App\Controller\CommentController;
use App\Controller\HealthController;
use App\Controller\ProductController;
use App\Database\DatabaseUnavailableException;
use App\Product\ProductApiUnavailableException;
use App\Product\ProductNotFoundException;
use App\Security\InvalidCsrfTokenException;
use App\View\View;

/**
 * Turns every request into a response (US-22, US-31): the routes, the error pages and the
 * security headers, in one place.
 *
 * public/index.php builds it with the real classes and sends the response; the functional
 * tests build it with a fake product provider. Business exceptions are thrown where the
 * problem is found and caught only here, to choose the error page.
 */
final readonly class Application
{
    /**
     * Only this site's scripts, styles and forms; images from FakeStore. No inline style or
     * script anywhere: the strongest defence against XSS after escaping.
     */
    private const string CONTENT_SECURITY_POLICY = "default-src 'self'; img-src 'self' https://fakestoreapi.com; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'";

    /** Sent once here, on every response, error pages included. */
    private const array SECURITY_HEADERS = [
        'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
    ];

    private Router $router;

    public function __construct(
        ProductController $productController,
        CommentController $commentController,
        HealthController $healthController,
        private View $view,
    ) {
        $this->router = new Router();
        $this->router->get('/', static fn(Request $request): Response => $productController->list($request));
        $this->router->get('/products/{id}', static fn(Request $request, array $parameters): Response => $productController->show($request, $parameters['id']));
        // A review is always reached through its product: /products/{productId}/comments/{commentId}.
        $this->router->post('/products/{productId}/comments', static fn(Request $request, array $parameters): Response => $commentController->create($request, $parameters['productId']));
        $this->router->get('/products/{productId}/comments/{commentId}/edit', static fn(Request $request, array $parameters): Response => $commentController->edit($request, $parameters['productId'], $parameters['commentId']));
        $this->router->post('/products/{productId}/comments/{commentId}', static fn(Request $request, array $parameters): Response => $commentController->update($request, $parameters['productId'], $parameters['commentId']));
        $this->router->post('/products/{productId}/comments/{commentId}/delete', static fn(Request $request, array $parameters): Response => $commentController->delete($request, $parameters['productId'], $parameters['commentId']));
        $this->router->get('/health', static fn(): Response => $healthController->check());
    }

    /**
     * The response to a request: the page, or the error page chosen from the exception.
     * Never throws: every failure becomes a page, with the security headers.
     */
    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (\Throwable $exception) {
            $response = $this->errorResponse($exception);
        }

        return $response->withHeaders(self::SECURITY_HEADERS);
    }

    /**
     * The only place where business exceptions are turned into pages.
     */
    private function errorResponse(\Throwable $exception): Response
    {
        return match (true) {
            $exception instanceof ProductNotFoundException => $this->errorPage(404, 'Produit introuvable', "Ce produit n'existe pas ou n'est plus au catalogue."),
            $exception instanceof CommentNotFoundException => $this->errorPage(404, 'Avis introuvable', "Cet avis n'existe pas ou a été supprimé."),
            $exception instanceof PageNotFoundException => $this->errorPage(404, 'Page introuvable', "Cette page n'existe pas."),
            // Nothing was saved: the visitor reloads the page, which gives a fresh form and token.
            $exception instanceof InvalidCsrfTokenException => $this->errorPage(403, 'Formulaire expiré', 'Rechargez la page, puis envoyez à nouveau le formulaire.'),
            // The details go to the logs, never to the page.
            $exception instanceof ProductApiUnavailableException, $exception instanceof DatabaseUnavailableException
                => $this->logged('503: ' . $exception->getMessage(), $this->errorPage(503, 'Le catalogue est momentanément indisponible', 'Merci de réessayer dans quelques instants.')),
            // Last resort for a bug: logged with its trace, a neutral page for the visitor.
            default => $this->logged('500: ' . $exception, $this->errorPage(500, 'Une erreur est survenue', 'Merci de réessayer dans quelques instants.')),
        };
    }

    private function logged(string $message, Response $response): Response
    {
        error_log($message);

        return $response;
    }

    /**
     * Real status code, noindex, and never cached: the page must disappear as soon as the problem is fixed.
     */
    private function errorPage(int $statusCode, string $heading, string $message): Response
    {
        $html = $this->view->page('error', [
            'title' => $heading,
            'noindex' => true,
            'statusCode' => $statusCode,
            'heading' => $heading,
            'message' => $message,
        ]);

        return Response::html($statusCode, $html, ['Cache-Control' => 'no-store']);
    }
}
