<?php

declare(strict_types=1);

namespace App\Http;

use App\Controller\HealthController;
use App\Controller\ProductController;
use App\Database\DatabaseUnavailableException;
use App\Product\ProductApiUnavailableException;
use App\Product\ProductNotFoundException;
use App\View\View;

/**
 * Turns every request into a response (US-22): the routes, the error pages and the
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

    private Router $router;

    public function __construct(
        ProductController $productController,
        HealthController $healthController,
        private View $view,
    ) {
        $this->router = new Router();
        $this->router->get('/', static fn(Request $request): Response => $productController->list($request));
        $this->router->get('/products/{id}', static fn(Request $request, array $parameters): Response => $productController->show($parameters['id']));
        $this->router->get('/health', static fn(): Response => $healthController->check());
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (ProductNotFoundException) {
            $response = $this->errorPage(404, 'Produit introuvable', "Ce produit n'existe pas ou n'est plus au catalogue.");
        } catch (PageNotFoundException) {
            $response = $this->errorPage(404, 'Page introuvable', "Cette page n'existe pas.");
        } catch (ProductApiUnavailableException|DatabaseUnavailableException $exception) {
            // The details are in the logs, never on the page.
            error_log('503: ' . $exception->getMessage());
            $response = $this->errorPage(503, 'Le catalogue est momentanément indisponible', 'Merci de réessayer dans quelques instants.');
        } catch (\Throwable $exception) {
            // Last resort for a bug: logged with its trace, a neutral page for the visitor.
            error_log('500: ' . $exception);
            $response = $this->errorPage(500, 'Une erreur est survenue', 'Merci de réessayer dans quelques instants.');
        }

        return $response->withHeaders([
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ]);
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
