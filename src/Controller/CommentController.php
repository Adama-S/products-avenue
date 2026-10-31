<?php

declare(strict_types=1);

namespace App\Controller;

use App\Comment\Comment;
use App\Comment\CommentForm;
use App\Comment\CommentNotFoundException;
use App\Comment\CommentNotice;
use App\Comment\CommentRepository;
use App\Comment\CommentValidator;
use App\Http\PathId;
use App\Http\Request;
use App\Http\Response;
use App\Pagination\Pagination;
use App\Product\Product;
use App\Product\ProductNotFoundException;
use App\Product\ProductProviderInterface;
use App\Security\Csrf;
use App\Security\InvalidCsrfTokenException;
use App\View\View;

/**
 * Adds, edits and deletes reviews from the product page (US-31).
 *
 * Every form is checked (CSRF token, then each field) and answered with a 303 redirect
 * (Post/Redirect/Get): a refresh or a double click never posts the review twice.
 * Editing and deleting are open to everyone, a deliberate choice explained in the README.
 */
final readonly class CommentController
{
    public function __construct(
        private ProductProviderInterface $products,
        private CommentRepository $comments,
        private CommentValidator $validator,
        private Csrf $csrf,
        private ProductPage $productPage,
        private View $view,
    ) {}

    /**
     * POST /products/{productId}/comments. Refused input: the product page again, with the errors (422).
     *
     * @throws InvalidCsrfTokenException when the form has not the session token (403)
     * @throws ProductNotFoundException when the product does not exist: nothing is saved (404)
     */
    public function create(Request $request, string $productId): Response
    {
        $this->checkCsrfToken($request);
        // No foreign key on the product (it lives in the API): its existence is checked here.
        $product = $this->product($productId);

        $form = $this->validator->validate($request->post('author'), $request->post('content'));
        if (!$form->isValid()) {
            return $this->productPage->render($product, 1, $form, null, 422);
        }

        $this->comments->create($product->id, $form->author, $form->content);

        // The new review is the newest: it is at the top of page 1.
        return self::redirect(ProductPage::url($product->id, 1, CommentNotice::Published));
    }

    /**
     * GET /products/{productId}/comments/{commentId}/edit: the form, pre-filled.
     *
     * @throws ProductNotFoundException|CommentNotFoundException (404)
     */
    public function edit(Request $request, string $productId, string $commentId): Response
    {
        $product = $this->product($productId);
        $comment = $this->comment($product, $commentId);

        return $this->editPage($product, $comment, CommentForm::fromComment($comment), self::pageOfOrigin($request->query('comments_page')), 200);
    }

    /**
     * POST /products/{productId}/comments/{commentId}: back to the review page the visitor came from.
     *
     * @throws InvalidCsrfTokenException (403)
     * @throws ProductNotFoundException|CommentNotFoundException (404)
     */
    public function update(Request $request, string $productId, string $commentId): Response
    {
        $this->checkCsrfToken($request);
        $product = $this->product($productId);
        $comment = $this->comment($product, $commentId);
        $page = self::pageOfOrigin($request->post('comments_page'));

        $form = $this->validator->validate($request->post('author'), $request->post('content'));
        if (!$form->isValid()) {
            return $this->editPage($product, $comment, $form, $page, 422);
        }

        $this->comments->update($comment->id, $product->id, $form->author, $form->content);

        return self::redirect(ProductPage::url($product->id, $page, CommentNotice::Updated));
    }

    /**
     * POST /products/{productId}/comments/{commentId}/delete. POST only: a link (GET) could be
     * followed by a crawler or triggered from another site.
     *
     * @throws InvalidCsrfTokenException (403)
     * @throws ProductNotFoundException|CommentNotFoundException (404)
     */
    public function delete(Request $request, string $productId, string $commentId): Response
    {
        $this->checkCsrfToken($request);
        $product = $this->product($productId);
        $comment = $this->comment($product, $commentId);

        $this->comments->delete($comment->id, $product->id);

        // The page of origin may be gone (its only review was deleted): the last page left.
        $lastPage = Pagination::of($this->comments->countByProduct($product->id), ProductPage::COMMENTS_PER_PAGE, 1)->pageCount;
        $page = min(self::pageOfOrigin($request->post('comments_page')), $lastPage);

        return self::redirect(ProductPage::url($product->id, $page, CommentNotice::Deleted));
    }

    /**
     * @throws InvalidCsrfTokenException
     */
    private function checkCsrfToken(Request $request): void
    {
        if (!$this->csrf->isValid($request->post(Csrf::FIELD))) {
            throw new InvalidCsrfTokenException('Missing or invalid CSRF token.');
        }
    }

    /**
     * @throws ProductNotFoundException
     */
    private function product(string $id): Product
    {
        $productId = PathId::parse($id) ?? throw new ProductNotFoundException(\sprintf('Invalid product id "%s".', $id));

        return $this->products->getById($productId);
    }

    /**
     * The review, only if it belongs to this product: /products/2/comments/{a review of product 1} is a 404.
     *
     * @throws CommentNotFoundException
     */
    private function comment(Product $product, string $id): Comment
    {
        $commentId = PathId::parse($id);
        $comment = $commentId === null ? null : $this->comments->findForProduct($commentId, $product->id);

        return $comment ?? throw new CommentNotFoundException(\sprintf('No review %s for product %d.', $id, $product->id));
    }

    private function editPage(Product $product, Comment $comment, CommentForm $form, int $commentsPage, int $statusCode): Response
    {
        $html = $this->view->page('edit-review', [
            'title' => 'Modifier un avis',
            'noindex' => true,
            'product' => $product,
            'comment' => $comment,
            'form' => $form,
            'commentsPage' => $commentsPage,
            'backUrl' => ProductPage::url($product->id, $commentsPage) . '#avis',
            'csrfToken' => $this->csrf->token(),
        ]);

        return Response::html($statusCode, $html, ['Cache-Control' => 'no-store']);
    }

    /**
     * The review page to go back to, sent by the form: anything invalid means page 1.
     */
    private static function pageOfOrigin(?string $value): int
    {
        return PathId::parse($value ?? '') ?? 1;
    }

    /**
     * 303 See Other: the browser follows with a GET, so a refresh does not post again.
     */
    private static function redirect(string $url): Response
    {
        return new Response(303, '', ['Location' => $url . '#avis', 'Cache-Control' => 'no-store']);
    }
}
