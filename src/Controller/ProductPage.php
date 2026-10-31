<?php

declare(strict_types=1);

namespace App\Controller;

use App\Comment\CommentForm;
use App\Comment\CommentNotice;
use App\Comment\CommentRepository;
use App\Http\Response;
use App\Pagination\Pagination;
use App\Product\Product;
use App\Security\Csrf;
use App\View\View;

/**
 * Renders the product page with one page of its reviews and the review form (US-23, US-31).
 *
 * Shared by two controllers: ProductController shows the page, CommentController shows it
 * again with the errors when a review is refused (422), so the visitor keeps what they typed.
 */
final readonly class ProductPage
{
    /** Reviews per page on the product page: fixed, not a setting. */
    public const int COMMENTS_PER_PAGE = 10;

    public function __construct(
        private CommentRepository $comments,
        private Csrf $csrf,
        private View $view,
    ) {}

    /**
     * Never cached (no-store): the page carries the visitor's own CSRF token, confirmation
     * messages and reviews that change; a shared cache would serve one visitor's token to another.
     *
     * @throws \App\Http\PageNotFoundException when the review page does not exist (404),
     *                                         except page 1 of a product without reviews
     */
    public function render(Product $product, int $commentsPage, CommentForm $form, ?CommentNotice $notice, int $statusCode = 200): Response
    {
        $pagination = Pagination::of($this->comments->countByProduct($product->id), self::COMMENTS_PER_PAGE, $commentsPage);
        $description = self::excerpt($product->description === '' ? $product->title : $product->description);
        $url = $this->view->absoluteUrl(self::url($product->id, 1));

        $html = $this->view->page('product', [
            'title' => $product->title . ', ' . $product->price->format(),
            'description' => $description,
            // The canonical never carries comments_page: one indexed URL per product.
            'canonicalPath' => self::url($product->id, 1),
            'openGraph' => self::openGraph($product, $description, $url),
            'structuredData' => [self::productSchema($product, $url), $this->breadcrumbSchema($product, $url)],
            'product' => $product,
            'comments' => $this->comments->findPageByProduct($product->id, self::COMMENTS_PER_PAGE, $pagination->offset()),
            'pagination' => $pagination,
            'commentsPageUrl' => static fn(int $page): string => self::url($product->id, $page) . '#avis',
            'form' => $form,
            'notice' => $notice,
            'csrfToken' => $this->csrf->token(),
        ]);

        return Response::html($statusCode, $html, ['Cache-Control' => 'no-store']);
    }

    /**
     * URL of the product page; page 1 of the reviews has no parameter.
     */
    public static function url(int $productId, int $commentsPage, ?CommentNotice $notice = null): string
    {
        $query = http_build_query(['comments_page' => $commentsPage > 1 ? $commentsPage : null, 'review' => $notice?->value]);

        return '/products/' . $productId . ($query === '' ? '' : '?' . $query);
    }

    /**
     * Open Graph: the preview shown when the page is shared (title, image, price).
     *
     * @return array<string, string>
     */
    private static function openGraph(Product $product, string $description, string $url): array
    {
        return array_filter([
            'og:type' => 'product',
            'og:title' => $product->title,
            'og:description' => $description,
            'og:url' => $url,
            'og:image' => $product->imageUrl ?? '',
            'og:site_name' => 'Products Avenue',
            'og:locale' => 'fr_FR',
            'product:price:amount' => $product->price->decimal(),
            'product:price:currency' => $product->price->currency,
        ], static fn(string $content): bool => $content !== '');
    }

    /**
     * schema.org Product: lets search engines show the price in their results.
     * No availability: FakeStore does not give the stock.
     *
     * @return array<string, mixed>
     */
    private static function productSchema(Product $product, string $url): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'description' => $product->description,
            'image' => $product->imageUrl,
            'url' => $url,
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->price->decimal(),
                'priceCurrency' => $product->price->currency,
                'url' => $url,
            ],
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * schema.org BreadcrumbList: the same levels as the visible breadcrumb.
     *
     * @return array<string, mixed>
     */
    private function breadcrumbSchema(Product $product, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Catalogue', 'item' => $this->view->absoluteUrl('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $product->title, 'item' => $url],
            ],
        ];
    }

    /**
     * Meta description: search engines show about 155 characters; cut on a word, never mid-word.
     */
    private static function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= 155) {
            return $text;
        }

        $cut = mb_substr($text, 0, 154);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $lastSpace === false ? 154 : $lastSpace), ' ,.;:') . '…';
    }
}
