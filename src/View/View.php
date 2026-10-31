<?php

declare(strict_types=1);

namespace App\View;

/**
 * Renders the PHP templates of templates/ (US-22).
 *
 * Templates only display: no business logic, every value escaped with e(). A page is a
 * template rendered inside the common layout (header, footer, SEO tags).
 */
final readonly class View
{
    public function __construct(
        private string $templatesDirectory,
        private string $appUrl,
    ) {}

    /**
     * Renders a template on its own (a page body, or a partial from inside a template).
     *
     * @param array<string, mixed> $data variables of the template
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->templatesDirectory . '/' . $template . '.php';

        ob_start();
        try {
            // Template scope: only $data and $this (to render partials).
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    /**
     * Renders a full HTML page: the template inside the layout.
     *
     * @param array<string, mixed> $data variables of the template, plus the layout's:
     *                                   title, description, canonicalPath (?string),
     *                                   noindex (bool), currentNav (?string),
     *                                   openGraph (property => content), structuredData (JSON-LD blocks)
     */
    public function page(string $template, array $data): string
    {
        return $this->render('layout', ['content' => $this->render($template, $data)] + $data + [
            'description' => '',
            'canonicalPath' => null,
            'noindex' => false,
            'currentNav' => null,
            'openGraph' => [],
            'structuredData' => [],
        ]);
    }

    /**
     * Structured data (JSON-LD) for a <script type="application/ld+json"> block (US-24).
     *
     * Built with json_encode, never by hand. JSON_HEX_TAG writes < and > as < and >:
     * a product title from the API containing "</script>" cannot close the block and inject
     * HTML. The block is data, not code: the Content-Security-Policy does not block it.
     *
     * @param array<string, mixed> $data
     */
    public static function jsonLd(array $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Absolute URL built from APP_URL, for canonical links and structured data.
     */
    public function absoluteUrl(string $path): string
    {
        return $this->appUrl . $path;
    }
}
