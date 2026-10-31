<?php

declare(strict_types=1);

namespace App\Controller;

use App\Comment\CommentRepository;
use App\Http\Request;
use App\Http\Response;
use App\Pagination\Pagination;
use App\Product\PerPage;
use App\Product\Product;
use App\Product\ProductNotFoundException;
use App\Product\ProductProviderInterface;
use App\View\View;

/**
 * Catalogue and product pages (US-22, US-23).
 *
 * Thin: reads the request, asks the provider and the repository, hands the data to a
 * template. No SQL, no HTML here.
 */
final readonly class ProductController
{
    /** HTTP cache of the catalogue, same lifetime as the API cache: 5 minutes. */
    private const int CATALOGUE_MAX_AGE_SECONDS = 300;

    public function __construct(
        private ProductProviderInterface $products,
        private CommentRepository $comments,
        private View $view,
        private int $defaultPerPage,
    ) {}

    /**
     * The catalogue, one page at a time: ?page=N, and ?per_page= among 10, 20 or 30.
     *
     * FakeStore has no offset: the whole list comes from the cache and is sliced here,
     * so changing page never calls the API again.
     *
     * @throws \App\Http\PageNotFoundException when the page does not exist (404)
     * @throws \App\Product\ProductApiUnavailableException when the catalogue cannot be read (503)
     * @throws \App\Database\DatabaseUnavailableException when the review counts cannot be read (503)
     */
    public function list(Request $request): Response
    {
        $page = Pagination::pageFromQuery($request->query('page'));
        $chosenPerPage = PerPage::fromQuery($request->query('per_page'));

        $products = $this->products->getAll();
        $pagination = Pagination::of(\count($products), $chosenPerPage->value ?? $this->defaultPerPage, $page);
        $pageProducts = $pagination->slice($products);

        $html = $this->view->page('catalogue', [
            'title' => $page === 1 ? 'Catalogue' : 'Catalogue, page ' . $page,
            'description' => $this->description(\count($products), $page),
            // The canonical never carries per_page: one indexed URL per page of the catalogue.
            'canonicalPath' => $this->catalogueUrl($page, null),
            'currentNav' => 'catalogue',
            'products' => $pageProducts,
            'reviewCounts' => $this->comments->countByProducts(array_map(static fn(Product $product): int => $product->id, $pageProducts)),
            'pagination' => $pagination,
            'perPageOptions' => $this->perPageOptions(),
            'pageUrl' => fn(int $number): string => $this->catalogueUrl($number, $chosenPerPage),
        ]);

        return $this->cacheable($request, $html);
    }

    /**
     * The product page: /products/{id}.
     *
     * Never cached (no-store): it shows confirmation messages, reviews that change, and
     * will carry a CSRF token of its own for each visitor; a shared cache would serve one
     * visitor's token to another.
     *
     * @throws ProductNotFoundException when the id is invalid or unknown (404)
     * @throws \App\Product\ProductApiUnavailableException when the product cannot be read (503)
     */
    public function show(string $id): Response
    {
        $product = $this->products->getById(self::productId($id));

        $html = $this->view->page('product', [
            'title' => $product->title . ', ' . $product->price->format(),
            'description' => self::excerpt($product->description === '' ? $product->title : $product->description),
            'canonicalPath' => '/products/' . $product->id,
            'product' => $product,
        ]);

        return Response::html(200, $html, ['Cache-Control' => 'no-store']);
    }

    /**
     * A product id from the URL, checked before it goes anywhere near the API URL:
     * a plain positive integer, or the product does not exist.
     *
     * @throws ProductNotFoundException
     */
    private static function productId(string $parameter): int
    {
        if (preg_match('/^[1-9]\d{0,8}$/', $parameter) !== 1) {
            throw new ProductNotFoundException(\sprintf('Invalid product id "%s".', $parameter));
        }

        return (int) $parameter;
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

    /**
     * Public HTTP cache with an ETag: a browser or a CDN may keep the page 5 minutes, then
     * revalidate it; an unchanged page costs a 304 without a body. Possible only because
     * the catalogue opens no session: no cookie, nothing personal in the page.
     *
     * The ETag is weak (W/): Apache may gzip the page, and the compressed bytes differ while
     * the content is the same. If-None-Match is compared the weak way, as HTTP requires.
     */
    private function cacheable(Request $request, string $html): Response
    {
        $hash = hash('xxh128', $html);
        $headers = ['Cache-Control' => 'public, max-age=' . self::CATALOGUE_MAX_AGE_SECONDS, 'ETag' => 'W/"' . $hash . '"'];

        foreach (explode(',', $request->header('If-None-Match') ?? '') as $tag) {
            if (trim(str_replace('W/', '', $tag)) === '"' . $hash . '"') {
                return new Response(304, '', $headers);
            }
        }

        return Response::html(200, $html, $headers);
    }

    /**
     * Page 1 is '/', without parameters; the chosen page size is kept while browsing.
     */
    private function catalogueUrl(int $page, ?PerPage $perPage): string
    {
        $query = http_build_query(['page' => $page > 1 ? $page : null, 'per_page' => $perPage?->value]);

        return '/' . ($query === '' ? '' : '?' . $query);
    }

    /**
     * The offered sizes, plus PRODUCTS_PER_PAGE when it is not one of them (8 in the acceptance test).
     *
     * @return list<int>
     */
    private function perPageOptions(): array
    {
        $options = array_unique([$this->defaultPerPage, ...array_map(static fn(PerPage $size): int => $size->value, PerPage::cases())]);
        sort($options);

        return $options;
    }

    private function description(int $productCount, int $page): string
    {
        return \sprintf('Parcourez les %d produits du catalogue Products Avenue : prix et avis clients', $productCount)
            . ($page > 1 ? ', page ' . $page . '.' : '.');
    }
}
