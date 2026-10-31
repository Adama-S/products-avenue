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

        $html = $this->view->page('product', [
            'title' => $product->title . ', ' . $product->price->format(),
            'description' => self::excerpt($product->description === '' ? $product->title : $product->description),
            // The canonical never carries comments_page: one indexed URL per product.
            'canonicalPath' => self::url($product->id, 1),
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
