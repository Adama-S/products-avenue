<?php

declare(strict_types=1);

namespace App\Pagination;

use App\Http\PageNotFoundException;

/**
 * One page of a list: current page, number of pages, items of the page (US-22, US-31).
 *
 * Used for the catalogue (sliced in the application: FakeStore has no offset) and for the
 * reviews of a product (sliced by SQL with offset()). A page that does not exist is a 404,
 * except page 1 of an empty list, which is a valid empty page.
 */
final readonly class Pagination
{
    public int $pageCount;

    /**
     * @throws PageNotFoundException when the page is below 1 or after the last page
     */
    private function __construct(
        public int $totalItems,
        public int $perPage,
        public int $currentPage,
    ) {
        if ($perPage < 1) {
            throw new \InvalidArgumentException(\sprintf('A page holds at least one item, got %d.', $perPage));
        }

        $this->pageCount = max(1, (int) ceil($totalItems / $perPage));
        if ($currentPage < 1 || $currentPage > $this->pageCount) {
            throw new PageNotFoundException(\sprintf('Page %d does not exist (%d pages).', $currentPage, $this->pageCount));
        }
    }

    /**
     * @throws PageNotFoundException when the page does not exist
     */
    public static function of(int $totalItems, int $perPage, int $currentPage): self
    {
        return new self($totalItems, $perPage, $currentPage);
    }

    /**
     * Reads a page number from the URL: missing means page 1; anything but a plain
     * positive integer is a 404, so each page has a single URL (?page=01 is not ?page=1).
     *
     * @throws PageNotFoundException when the parameter is not a positive integer
     */
    public static function pageFromQuery(?string $parameter): int
    {
        if ($parameter === null) {
            return 1;
        }

        // Nine digits at most: far beyond any real page, and no integer overflow.
        if (preg_match('/^[1-9]\d{0,8}$/', $parameter) !== 1) {
            throw new PageNotFoundException(\sprintf('Invalid page number "%s".', $parameter));
        }

        return (int) $parameter;
    }

    /**
     * Number of items before the current page (SQL OFFSET, or the slice start).
     */
    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    /**
     * False on the first page: no « Précédent » link there.
     */
    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * False on the last page: no « Suivant » link there.
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->pageCount;
    }

    /**
     * Position of the first item of the page in the whole list (« Avis 11 à 20 sur 23 »), 0 when empty.
     */
    public function firstItemNumber(): int
    {
        return $this->totalItems === 0 ? 0 : $this->offset() + 1;
    }

    /**
     * Position of the last item of the page in the whole list, 0 when empty.
     */
    public function lastItemNumber(): int
    {
        return min($this->offset() + $this->perPage, $this->totalItems);
    }

    /**
     * @return list<int>
     */
    public function pages(): array
    {
        return range(1, $this->pageCount);
    }

    /**
     * The items of the current page, out of the whole list.
     *
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function slice(array $items): array
    {
        return \array_slice($items, $this->offset(), $this->perPage);
    }
}
